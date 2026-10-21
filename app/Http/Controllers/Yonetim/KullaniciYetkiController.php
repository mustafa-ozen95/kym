<?php

declare(strict_types=1);

namespace App\Http\Controllers\Yonetim;

use App\Http\Controllers\Controller;
use App\Models\KullaniciYetkisi;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\Yetki\YetkiCozumleyici;
use App\Services\Yetki\YetkiSozlugu;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kişiye özel yetki istisnaları.
 *
 * Rolün şablonunun ÜSTÜNE yazar: "editör ama hesapları da onaylasın" ya da
 * "yönetici yardımcısı ama posta ayarına dokunmasın" gibi durumlar yeni rol
 * açmadan çözülür.
 *
 * Yetkilendirme: yetki dağıtmak, rol atamakla aynı ağırlıkta bir iştir ve
 * aynı yetkiyi ister (`kullanici.rol`). "Hesapları onayla" bunu KAPSAMAZ;
 * kapsasaydı onaylayan kişi kendine istediği yetkiyi yazabilirdi.
 */
class KullaniciYetkiController extends Controller implements HasMiddleware
{
    public function __construct(private YetkiSozlugu $sozluk) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware(['auth', 'can:kullanici.rol'])];
    }

    /**
     * Kişiye özel yetkiler — KENDİ SAYFASI.
     *
     * Listedeki satırın altına katlanırsa kaynak sayısı arttıkça tablo
     * okunamaz hâle gelir ve hangi kutucuğun kime ait olduğu kaybolur.
     */
    public function show(User $user, #[CurrentUser] $kullanici): View
    {
        $user->loadMissing(['rol', 'yetkiler']);

        return view('yonetim.kullanici-yetkileri', [
            'kullanici' => $user,
            'kaynaklar' => $this->sozluk->ekranSatirlari(YetkiCozumleyici::yetkiDagitilabilirMi()),

            /* Rolün verdikleri AYRI gösterilir: istisna, şablonun üstüne yazar. */
            'sablon' => $user->rol?->yetenekler() ?? [],
            'istisnalar' => $user->yetkiler->pluck('etki', 'yetenek')->all(),

            /* Kendi yetkisini değiştiremezsin (bkz. update). */
            'kendisi' => $user->is($kullanici),

            /*
             * Yetki dağıtımı açık mı? Kapalıyken ekran kutu göstermez:
             * seçilen bir istisna hiçbir şey açmıyordu ve bunu seçen kişi
             * ancak biri "neden giremiyorum" diye sorunca öğrenirdi.
             */
            'yetkiDagitilabilir' => YetkiCozumleyici::yetkiDagitilabilirMi(),
        ]);
    }

    public function update(
        Request $request,
        User $user,
        ActivityRecorder $recorder,
        YetkiCozumleyici $cozumleyici,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        /*
         * KENDİ YETKİNİ DEĞİŞTİREMEZSİN.
         *
         * Değiştirebilseydi "yetki dağıt" yetkisi, kendine her şeyi verme
         * yetkisine dönüşürdü.
         */
        if ($user->is($kullanici)) {
            return back()->with('error', 'Kendi yetkilerinizi bu ekrandan değiştiremezsiniz.');
        }

        $veri = $request->validate([
            'yetkiler' => ['array'],
            /* Boş = "rolden gelsin": satır hiç yazılmaz. */
            'yetkiler.*' => ['nullable', 'string', 'in:'.KullaniciYetkisi::IZIN.','.KullaniciYetkisi::RED],
        ]);

        /*
         * Tanınmayan anahtar yazılmaz (bkz. RolController::veri).
         *
         * Merkezin yönetimi yalnızca yöneticideyken YÖNETİM yetenekleri de
         * tanınmaz: ekran o satırları basmıyor ama istek elle kurulabilir.
         * Kutucuk ve salon erişimleri kalır.
         */
        $taninan = $this->sozluk->tumYetenekler();

        if (! YetkiCozumleyici::yetkiDagitilabilirMi()) {
            $taninan = $this->sozluk->yonetimiEle($taninan);
        }
        $satirlar = [];

        foreach ((array) ($veri['yetkiler'] ?? []) as $yetenek => $etki) {
            if (filled($etki) && in_array((string) $yetenek, $taninan, true)) {
                $satirlar[] = ['yetenek' => (string) $yetenek, 'etki' => $etki];
            }
        }

        /*
         * Sil-ve-yaz TEK İŞLEMDE: yarıda kalırsa kullanıcı yetkisiz kalırdı
         * ve bunu ancak o kişi bir kapıya çarptığında fark ederdik.
         */
        DB::transaction(function () use ($user, $satirlar): void {
            KullaniciYetkisi::where('user_id', $user->id)->delete();

            foreach ($satirlar as $satir) {
                KullaniciYetkisi::create([...$satir, 'user_id' => $user->id]);
            }
        });

        $cozumleyici->unut($user);

        $recorder->record(
            'yetki-degisti',
            $user->email.' için '.count($satirlar).' kişisel yetki tanımlandı',
            $kullanici,
        );

        return redirect()->route('yonetim.kullanicilar.yetkiler', $user)
            ->with('status', $user->name.' için yetkiler güncellendi.');
    }
}
