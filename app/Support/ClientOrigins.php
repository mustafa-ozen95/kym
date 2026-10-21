<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OauthClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * KAYITLI UYGULAMALARIN KÖKENLERİ (CSP `form-action` için).
 *
 * NEDEN VAR: yalnız `form-action 'self'` girişi tümüyle kırar ve sebebi
 * görünmez. Tarayıcı bu kısıtı yalnızca formun gittiği adrese değil,
 * FORM GÖNDERİMİNDEN BAŞLAYAN TÜM YÖNLENDİRME ZİNCİRİNE uygular. Giriş
 * formu kendi kökenimize gidiyor, oradan /oauth/authorize'a, oradan da
 * istemcinin adresine dönüyor — son adım başka köken olduğu için gezinti
 * iptal edilir.
 *
 * Belirti (Chromium): POST /giris 302 verir, oturum AÇILIR, ama
 * /oauth/authorize gezintisi `net::ERR_ABORTED` ile düşer ve konsolda
 * "Sending form data to 'https://merkez.ornek.test/giris' violates ...
 * form-action 'self'" yazar. Kullanıcı ekranda hâlâ giriş formunu gördüğü
 * için ikinci kez tıklar.
 *
 * LİSTE ELLE YAZILMAZ: kayıtlı istemcilerden türer. Elle yazılsaydı yeni bir
 * panel eklendiğinde girişi sessizce çalışmaz olurdu ve kimse burayı aramazdı.
 */
final class ClientOrigins
{
    private const ANAHTAR = 'guvenlik:istemci-kokenleri';

    /**
     * Boşlukla ayrılmış köken listesi (`https://panel-a.ornek.test ...`).
     *
     * Sürekli önbellekte durur: her istekte sorgu açmaya değmez. İstemci
     * kaydı değiştiğinde model olayı listeyi unutturur (bkz. OauthClient).
     */
    public static function csp(): string
    {
        /*
         * VERİTABANI DÜŞERSE BAŞLIK YİNE ÇIKAR.
         *
         * Bu liste her yanıtta okunuyor — HATA SAYFALARINDA DA. Sorgu
         * patlarsa burada patlaması, "veritabanına ulaşılamıyor" sayfasını
         * çıplak bir 500'e çevirirdi. Boş liste güvenli taraf: politika
         * yalnızca 'self' kalır (giriş zaten çalışmıyordur).
         */
        try {
            return self::hesapla();
        } catch (Throwable) {
            return '';
        }
    }

    public static function unut(): void
    {
        Cache::forget(self::ANAHTAR);
    }

    private static function hesapla(): string
    {
        return Cache::rememberForever(self::ANAHTAR, static fn (): string => OauthClient::query()
            ->where('revoked', false)
            ->get()
            ->flatMap(static fn (OauthClient $istemci): array => (array) $istemci->redirect_uris)
            ->map(static fn (mixed $adres): ?string => self::koken((string) $adres))
            ->filter()
            ->unique()
            ->sort()
            ->implode(' '));
    }

    /** Adresin yalnızca şema+ana bilgisayar+kapı kısmı; yol taşınmaz. */
    private static function koken(string $adres): ?string
    {
        $parca = parse_url($adres);

        if (! isset($parca['scheme'], $parca['host'])) {
            return null;
        }

        return $parca['scheme'].'://'.$parca['host'].(isset($parca['port']) ? ':'.$parca['port'] : '');
    }
}
