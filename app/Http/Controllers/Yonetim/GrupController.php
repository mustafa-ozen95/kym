<?php

declare(strict_types=1);

namespace App\Http\Controllers\Yonetim;

use App\Http\Controllers\Controller;
use App\Http\Requests\Yonetim\GrupRequest;
use App\Models\Grup;
use App\Models\Sistem;
use App\Services\ActivityRecorder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hızlı Erişim salonları.
 *
 * Salon listesi iki yerde birden sabitti (çizim betiği ve ayar dosyası); yeni
 * bir salon açmak iki dosyayı düzenlemek demekti ve ikisi ayrışırsa ekleme
 * ekranında görünüp sayfada çizilmeyen bir grup ortaya çıkıyordu.
 *
 * SALON KENDİ SAYFASI OLABİLİR (ör. "Salon A"): ayrı sayfa işaretli bir
 * salon, şifre yerine yetki sorar.
 */
class GrupController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:grup.gor'], only: ['index']),
            new Middleware(['auth', 'can:grup.ekle'], only: ['store']),
            new Middleware(['auth', 'can:grup.duzenle'], only: ['update', 'sirala']),
            new Middleware(['auth', 'can:grup.sil'], only: ['destroy']),
        ];
    }

    public function index(Request $request, #[CurrentUser] $kullanici): View
    {
        return view('yonetim.gruplar', [
            /* N+1: her satır kaç kutucuğu olduğunu yazıyor. */
            'gruplar' => Grup::withCount('sistemler')->sirali()->get(),
            'ekleyebilir' => $kullanici->can('grup.ekle'),
            'duzenleyebilir' => $kullanici->can('grup.duzenle'),
            'silebilir' => $kullanici->can('grup.sil'),

            /* Pencerenin ADRESİ vardır; doğrulama hatasında açık geri gelir. */
            'pencere' => $request->query('pencere') === 'ekle' ? 'ekle' : null,
            'duzenlenen' => $request->query('kayit')
                ? Grup::firstWhere('uuid', $request->query('kayit'))
                : null,
        ]);
    }

    public function store(GrupRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $grup = Grup::create($request->validated());

        $recorder->record('grup-eklendi', $grup->ad.' salonu açıldı', $kullanici);

        return redirect()->route('yonetim.gruplar')->with('status', $grup->ad.' salonu açıldı.');
    }

    public function update(GrupRequest $request, Grup $grup, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validated();

        /*
         * ANAHTAR DEĞİŞİRSE kutucuklar da taşınır.
         *
         * Kutucuk kaydı salonu anahtarıyla tanıyor; yalnızca salonu
         * güncellemek, içindeki her kutucuğu sahipsiz bırakır ve hepsi
         * sayfadan düşerdi.
         */
        if (($veri['anahtar'] ?? $grup->anahtar) !== $grup->anahtar) {
            Sistem::where('grup', $grup->anahtar)->update(['grup' => $veri['anahtar']]);
        }

        $grup->update($veri);

        $recorder->record('grup-guncellendi', $grup->ad.' salonu güncellendi', $kullanici);

        return redirect()->route('yonetim.gruplar')->with('status', $grup->ad.' salonu güncellendi.');
    }

    /**
     * Salonları sürükleyerek sıralar.
     *
     * Sayfadaki bölüm dizilimi bu sıraya bakıyor; sayı kutusuna elle yazmak
     * sırayı değiştirmenin dolambaçlı yoluydu.
     */
    public function sirala(Request $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            'sira' => ['required', 'array'],
            'sira.*' => ['string'],
        ]);

        $salonlar = Grup::pluck('id', 'uuid');
        $numara = 1;

        DB::transaction(function () use ($veri, $salonlar, &$numara): void {
            foreach ($veri['sira'] as $uuid) {
                if ($salonlar->has($uuid)) {
                    Grup::whereKey($salonlar[$uuid])->update(['sira' => $numara++]);
                }
            }
        });

        $recorder->record('grup-siralandi', 'Salonlar yeniden sıralandı', $kullanici);

        return back()->with('status', 'Salon sırası güncellendi.');
    }

    public function destroy(Grup $grup, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /*
         * Kutucuğu olan salon silinmez.
         *
         * Silinseydi içindeki kutucuklar var olmayan bir salona bakar ve
         * sayfadan sessizce düşerdi — kimse silindiklerini söylemeden.
         */
        if ($grup->sistemler()->exists()) {
            return back()->with('error', 'Bu salonda kutucuklar var; önce onları başka bir salona taşıyın.');
        }

        $ad = $grup->ad;
        $grup->delete();

        $recorder->record('grup-silindi', $ad.' salonu silindi', $kullanici);

        return redirect()->route('yonetim.gruplar')->with('status', $ad.' salonu silindi.');
    }
}
