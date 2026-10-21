<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\ActivityRecorder;
use App\Services\Permission\PermissionDictionary;
use App\Services\Permission\PermissionResolver;
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
class UserPermissionController extends Controller implements HasMiddleware
{
    public function __construct(private PermissionDictionary $sozluk) {}

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

        return view('admin.user-permissions', [
            'kullanici' => $user,
            'kaynaklar' => $this->sozluk->ekranSatirlari(PermissionResolver::yetkiDagitilabilirMi()),

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
            'yetkiDagitilabilir' => PermissionResolver::yetkiDagitilabilirMi(),
        ]);
    }

    public function update(
        Request $request,
        User $user,
        ActivityRecorder $recorder,
        PermissionResolver $cozumleyici,
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
            'yetkiler.*' => ['nullable', 'string', 'in:'.UserPermission::IZIN.','.UserPermission::RED],
        ]);

        /*
         * Tanınmayan anahtar yazılmaz (bkz. RoleController::veri).
         *
         * Merkezin yönetimi yalnızca yöneticideyken YÖNETİM yetenekleri de
         * tanınmaz: ekran o satırları basmıyor ama istek elle kurulabilir.
         * Kutucuk ve salon erişimleri kalır.
         */
        $taninan = $this->sozluk->tumYetenekler();

        if (! PermissionResolver::yetkiDagitilabilirMi()) {
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
            UserPermission::where('user_id', $user->id)->delete();

            foreach ($satirlar as $satir) {
                UserPermission::create([...$satir, 'user_id' => $user->id]);
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
