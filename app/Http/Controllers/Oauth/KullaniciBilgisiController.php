<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\OauthIstemci;
use App\Services\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Kimlik bilgisi ucu (userinfo).
 *
 * İstemci projeler jetonla buraya gelip "bu jeton kimin" sorusunu sorar ve
 * kendi oturumunu açar. Dönen alanlar KASITLI OLARAK AZ: kimliği kanıtlamak
 * için ad, e-posta ve değişmez bir kimlik yeter.
 *
 * ERİŞİM KAPISI BURADA KAPANIR. Hangi rolün hangi uygulamaya girebileceğine
 * MERKEZ karar verir (bkz. OauthIstemci::roller): rolü o uygulamanın
 * listesinde olmayan kişi jetonu almış olsa bile buradan 403 alır ve
 * uygulama oturum açamaz. Karar her panelin kendi yapılandırmasında dursaydı
 * aynı karar birkaç yerde yazılı olur, biri değişince öbürleri sessizce eski
 * kalırdı.
 *
 * İÇERİDEKİ YETKİ PROJENİN İŞİ. Merkez "bu kişi girebilir" der; ne
 * görebileceğine ve ne yapabileceğine projenin kendisi karar verir. Rol slug'ı
 * bilgi olarak döner, emir olarak değil.
 *
 * Yetkilendirme: rota `auth:api` altında; jetonsuz ya da süresi geçmiş
 * jetonla gelen istek 401 alır.
 */
class KullaniciBilgisiController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function __invoke(Request $request, ActivityRecorder $recorder): JsonResponse
    {
        $kullanici = $request->user();
        $istemci = $this->istemci($request);

        /*
         * ROLÜ BU UYGULAMAYA GİREMİYORSA BURADA DURUR.
         *
         * Yanıt 403 ve sebebi kodla söylenir: uygulama "merkez cevap vermedi"
         * ile "senin yetkin yok"u ayırt edebilmeli — birincisinde kendi
         * girişine düşer, ikincisinde kapıyı kapatır.
         *
         * Reddin kendisi kayda geçer: kimin nereye girmeye çalıştığı sorulacak.
         */
        if ($istemci !== null && ! $istemci->erisebilirMi($kullanici)) {
            $recorder->record(
                'projeye-giris-reddedildi',
                $kullanici->email.' → '.$istemci->name.' (rol: '.($kullanici->rol?->slug ?? 'rolsüz').')',
                $kullanici,
            );

            return response()->json([
                'kod' => 'rol_yetkisiz',
                'hata' => 'Bu uygulamaya erişim yetkiniz yok.',
            ], 403);
        }

        /*
         * "KİM NEREYE GİRDİ" BURADA YAZILIR.
         *
         * Merkezin kendi giriş kaydı yalnızca "merkeze girdi" der; hangi
         * projeye gidildiğini söylemez. Bu uç, girişin son adımıdır: istemci
         * jetonu alıp "bu jeton kimin" diye buraya sorar ve ancak ondan sonra
         * kendi oturumunu açar. Yani bu satır, o projeye girildiğinin kaydıdır.
         *
         * Aynı oturumda tekrar tekrar yazılmaz: jeton kısa ömürlü ve her giriş
         * yeni bir jeton demek (bkz. AppServiceProvider'daki jeton ömrü).
         */
        $recorder->record(
            'projeye-giris',
            $kullanici->email.' → '.($this->istemciAdi($request) ?? 'bilinmeyen uygulama'),
            $kullanici,
        );

        return response()->json([
            /* Değişmez kimlik: e-posta değişse bile eşleşme bozulmasın. */
            'sub' => $kullanici->uuid,
            'name' => $kullanici->name,
            'email' => $kullanici->email,
            'email_verified' => $kullanici->email_verified_at !== null,

            /*
             * Merkezdeki rolün SLUG'ı. Ad değil slug dönüyor: ad ekranda
             * değişebilir, slug yerleşik rollerde değişmez ve projelerdeki
             * karşılığıyla birebir aynı.
             */
            'rol' => $kullanici->rol?->slug,
            'rol_adi' => $kullanici->rol?->label,

            /*
             * HESAP KENDİLİĞİNDEN AÇILABİLİR Mİ?
             *
             * Uygulamada karşılığı olmayan bir kimlik geldiğinde hesabın
             * açılıp açılmayacağına da merkez karar verir; uygulama yalnızca
             * uygular. Karar uygulama başınadır (bkz. İstemciler ekranı):
             * bir uygulamada belli bir rol için açılması istenirken bir
             * başkasında kimse için istenmeyebilir.
             */
            'hesap_acilabilir' => $istemci?->hesapAcilabilirMi($kullanici) ?? false,
        ]);
    }

    /** Jetonu kullanan istemci; erişim kararı ona bağlı. */
    private function istemci(Request $request): ?OauthIstemci
    {
        $kimlik = $request->user()?->token()?->oauth_client_id;

        return $kimlik ? OauthIstemci::find($kimlik) : null;
    }

    /**
     * Jetonu hangi uygulama kullanıyor?
     *
     * Passport jetonu istemciye bağlı tutuyor; adı oradan okunur. İstemci
     * silinmiş ya da jeton bir şekilde sahipsizse kayıt yine yazılır —
     * "bilinmeyen uygulama" satırı, hiç satır olmamasından iyidir.
     */
    private function istemciAdi(Request $request): ?string
    {
        /*
         * Jetonun taşıdığı istemci KİMLİĞİ okunur, ilişki zinciri değil.
         *
         * `token()->client->name` da çalışıyor ama araya Passport'un
         * yönlendirme katmanı giriyor ve jetonun türüne göre değişiyor;
         * kimlik alanı her türde aynı yerde duruyor.
         */
        return $this->istemci($request)?->name;
    }
}
