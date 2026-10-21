<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ClientOrigins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Güvenlik başlıkları.
 *
 * nginx'te değil uygulamada durur: vekil değişince başlıkların kaybolmaması
 * için. Değerler config/security.php içindedir. Bağlı panellerde aynı ara
 * katman bulunur; biri değişirse ötekilere de bakın.
 *
 * Burası kimlik sağlayıcısı: çerçeveye alınmış bir giriş ekranı, kullanıcının parolayı
 * hangi siteye yazdığını göremediği bir giriş ekranıdır.
 *
 * VAR OLAN BAŞLIK EZİLMEZ: bir yanıt kendi kararını yazdıysa (örneğin bir uç
 * bilerek `no-referrer` diyorsa) burada gevşetmek, kararı sessizce geri almak
 * olurdu.
 *
 * Burada çerçeve muafiyeti olan bir uç yoktur.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $yanit = $next($request);

        foreach ((array) config('security.headers') as $ad => $deger) {
            if (filled($deger) && ! $yanit->headers->has($ad)) {
                $yanit->headers->set($ad, $this->doldur((string) $deger));
            }
        }

        $this->onbellekKapat($request, $yanit);

        return $yanit;
    }

    /**
     * Politikadaki `{istemciler}` yerine kayıtlı uygulamaların kökenleri.
     *
     * Değer yapılandırmada durur ama LİSTESİ VERİDEN gelir: yeni bir panel
     * kaydedildiğinde girişinin çalışması için kimsenin burayı açması
     * gerekmesin diye (bkz. App\Support\ClientOrigins).
     */
    private function doldur(string $deger): string
    {
        if (! str_contains($deger, '{istemciler}')) {
            return $deger;
        }

        $deger = str_replace('{istemciler}', ClientOrigins::csp(), $deger);

        /* Hiç istemci yoksa noktalı virgülün önünde boşluk kalmasın. */
        return (string) preg_replace('/ +(;|$)/', '$1', $deger);
    }

    /**
     * OTURUM AÇIK SAYFALAR DİSKE YAZILMAZ.
     *
     * İki sebep, ikisi de gerçek:
     *
     * 1. GÜVENLİK. Burası kimlik merkezi. Sayfada kullanıcı adları,
     *    e-posta adresleri, roller ve oturum bilgileri duruyor.
     *    `no-cache` tarayıcıya "kullanmadan önce sor" der ama KOPYAYI
     *    DİSKTEN SİLMEZ; ortak bir makinede çıkış yapıldıktan sonra da o
     *    dosya orada kalır. `no-store` hiç yazmamasını söyler.
     *
     * 2. ESKİ SAYFA. `no-cache` geri/ileri önbelleğini (bfcache) kapatmaz:
     *    kullanıcı geri gidip geldiğinde tarayıcı sayfayı SUNUCUYA HİÇ
     *    SORMADAN bellekten çizer. Panelde bir düğme eklendiğinde kullanıcı
     *    onu göremez ve haklı olarak "koymamışsın" der — sunucu koymuştur.
     *    `no-store` bu yolu da kapatır.
     *
     * YALNIZCA HTML ve yalnızca OTURUM AÇIKKEN: varlıklar (css/js) uzun
     * ömürlü önbelleğe muhtaç, giriş sayfasının da saklanacak bir şeyi yok.
     *
     * Var olan başlık yine ezilmez (bkz. yukarısı): parola ucu kendi
     * `no-store`unu zaten yazıyor.
     */
    private function onbellekKapat(Request $request, Response $yanit): void
    {
        /*
         * "Zaten var mı" diye BAKILMAZ: Laravel oturumlu her yanıta
         * kendiliğinden `no-cache, private` yazıyor, yani başlık her zaman
         * dolu olurdu ve bu kural hiç çalışmazdı. Bakılacak olan, yanıtın
         * BİLEREK no-store demiş olmasıdır — o zaman dokunulmaz.
         */
        if ($request->user() === null) {
            return;
        }

        if (str_contains((string) $yanit->headers->get('Cache-Control', ''), 'no-store')) {
            return;
        }

        $tur = (string) $yanit->headers->get('Content-Type', '');

        if (! str_contains($tur, 'text/html')) {
            return;
        }

        $yanit->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $yanit->headers->set('Pragma', 'no-cache');
    }
}
