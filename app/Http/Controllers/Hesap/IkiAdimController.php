<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hesap;

use App\Http\Controllers\Controller;
use App\Services\ActivityRecorder;
use App\Services\IkiAdim;
use App\Services\Totp;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Kullanıcının kendi ikinci adımı.
 *
 * Yetkilendirme: herkes YALNIZCA kendi hesabını kurar; rotalar `auth`
 * altında ve kullanıcı oturumdan gelir, adresten değil. Başkasının ikinci
 * adımını kurmanın ya da kapatmanın bir yolu yok.
 */
class IkiAdimController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('auth')];
    }

    public function index(Request $request, IkiAdim $ikiAdim, Totp $totp, #[CurrentUser] $kullanici): View
    {
        $kuruluyor = filled($kullanici->totp_secret) && ! $kullanici->ikiAdimliMi();

        return view('hesap.iki-adim', [
            'kurulu' => $kullanici->ikiAdimliMi(),
            'kuruluyor' => $kuruluyor,
            'anahtar' => $kuruluyor ? $kullanici->totp_secret : null,
            'uri' => $kuruluyor
                ? $totp->uri((string) $kullanici->totp_secret, (string) $kullanici->email, (string) config('app.name'))
                : null,
            'kalanKurtarma' => $ikiAdim->kalanKurtarma($kullanici),

            /* Kodlar YALNIZCA üretildikleri anda, bir kez gösterilir. */
            'kurtarmaKodlari' => $request->session()->get('kurtarma_kodlari'),
        ]);
    }

    public function store(Request $request, IkiAdim $ikiAdim, #[CurrentUser] $kullanici): RedirectResponse
    {
        /*
         * Kurmak da bir parola işlemidir, kapatmak gibi: baslat() onaylı
         * ikinci adımı ve kurtarma kodlarını SİLER. Oturumu parolasız ele
         * geçiren biri bu yoldan ikinci adımı düşürüp kendi cihazını
         * bağlayabilirdi.
         */
        $request->validate(['password' => ['required', 'current_password']]);

        $ikiAdim->baslat($kullanici);

        return redirect()->route('hesap.iki-adim');
    }

    public function onayla(Request $request, IkiAdim $ikiAdim, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Yeni anahtarı bağlamak da parola ister (bkz. store). */
        $request->validate([
            'kod' => ['required', 'string', 'max:10'],
            'password' => ['required', 'current_password'],
        ]);

        $kodlar = $ikiAdim->onayla($kullanici, (string) $request->string('kod'));

        if ($kodlar === null) {
            return back()->withErrors(['kod' => 'Kod doğrulanamadı; uygulamadaki güncel kodu girin.']);
        }

        $recorder->record('iki-adim-acildi', $kullanici->email.' ikinci adımı açtı', $kullanici);

        /*
         * Kodlar bir sonraki ekranda BİR KEZ gösterilir ve oturumdan silinir;
         * kalıcı olarak hiçbir yerde düz metin durmaz.
         */
        return redirect()->route('hesap.iki-adim')->with('kurtarma_kodlari', $kodlar);
    }

    public function destroy(Request $request, IkiAdim $ikiAdim, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Kapatmak da bir parola işlemidir: oturumu ele geçiren biri kapatamasın. */
        $request->validate(['password' => ['required', 'current_password']]);

        $ikiAdim->kapat($kullanici);
        $recorder->record('iki-adim-kapandi', $kullanici->email.' ikinci adımı kapattı', $kullanici);

        return redirect()->route('hesap.iki-adim')->with('status', 'İkinci adım kapatıldı.');
    }
}
