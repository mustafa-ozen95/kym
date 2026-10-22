<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\ActivityRecorder;
use App\Services\Totp;
use App\Services\TwoStep;
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
class TwoStepController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('auth')];
    }

    public function index(Request $request, TwoStep $ikiAdim, Totp $totp, #[CurrentUser] $kullanici): View
    {
        $kuruluyor = filled($kullanici->totp_secret) && ! $kullanici->ikiAdimliMi();

        return view('account.two-step', [
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

    public function store(Request $request, TwoStep $ikiAdim, #[CurrentUser] $kullanici): RedirectResponse
    {
        /*
         * Kurmak da bir parola işlemidir, kapatmak gibi: baslat() onaylı
         * ikinci adımı ve kurtarma kodlarını SİLER. Oturumu parolasız ele
         * geçiren biri bu yoldan ikinci adımı düşürüp kendi cihazını
         * bağlayabilirdi.
         */
        $request->validate(['password' => ['required', 'current_password']]);

        $ikiAdim->baslat($kullanici);

        return redirect()->route('account.two-step');
    }

    public function confirm(Request $request, TwoStep $ikiAdim, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Yeni anahtarı bağlamak da parola ister (bkz. store). */
        $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'current_password'],
        ]);

        $kodlar = $ikiAdim->onayla($kullanici, (string) $request->string('code'));

        if ($kodlar === null) {
            return back()->withErrors(['code' => 'Kod doğrulanamadı; uygulamadaki güncel kodu girin.']);
        }

        $recorder->record('iki-adim-acildi', $kullanici->email.' ikinci adımı açtı', $kullanici);

        /*
         * Kodlar bir sonraki ekranda BİR KEZ gösterilir ve oturumdan silinir;
         * kalıcı olarak hiçbir yerde düz metin durmaz.
         */
        return redirect()->route('account.two-step')->with('kurtarma_kodlari', $kodlar);
    }

    public function destroy(Request $request, TwoStep $ikiAdim, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Kapatmak da bir parola işlemidir: oturumu ele geçiren biri kapatamasın. */
        $request->validate(['password' => ['required', 'current_password']]);

        $ikiAdim->kapat($kullanici);
        $recorder->record('iki-adim-kapandi', $kullanici->email.' ikinci adımı kapattı', $kullanici);

        return redirect()->route('account.two-step')->with('status', 'İkinci adım kapatıldı.');
    }
}
