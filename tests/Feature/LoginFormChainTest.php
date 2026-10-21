<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OauthClient;
use App\Support\ClientOrigins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GİRİŞ FORMUNUN ZİNCİRİ KESİLMEZ.
 *
 * Giriş formu kendi kökenimize gider, ama gönderimden başlayan zincir
 * istemcinin adresinde biter: /giris → /oauth/authorize → istemci. Tarayıcı
 * CSP `form-action` kısıtını zincirin TAMAMINA uyguluyor; politika yalnızca
 * 'self' derken oturum açılıyor ama gezinti iptal ediliyor, kullanıcı ekranda
 * giriş formunu görmeye devam edip ikinci kez tıklıyordu.
 *
 * Belirti (Chromium): `net::ERR_ABORTED` ve konsolda
 * "Sending form data to 'https://merkez.ornek.test/giris' violates the following
 * Content Security Policy directive: form-action 'self'".
 */
class LoginFormChainTest extends TestCase
{
    use RefreshDatabase;

    private function politika(): string
    {
        return (string) $this->get(route('hizli-erisim', ['pencere' => 'giris']))
            ->headers->get('Content-Security-Policy');
    }

    private function istemciEkle(string $ad, string $adres): void
    {
        $this->artisan('kimlik:istemci-ekle', ['--ad' => $ad, '--adres' => [$adres]])
            ->assertSuccessful();
    }

    #[Test]
    public function form_action_kayitli_uygulamanin_kokenini_tasir(): void
    {
        $this->istemciEkle('Network', 'https://panel-b.ornek.test/giris/merkez/geri');

        $csp = $this->politika();

        $this->assertStringContainsString("form-action 'self' https://panel-b.ornek.test;", $csp);
    }

    /** Köken yeter: yol politikaya girmez, girse de tarayıcı yolu zaten okumaz. */
    #[Test]
    public function adresin_yolu_politikaya_yazilmaz(): void
    {
        $this->istemciEkle('Network', 'https://panel-b.ornek.test/giris/merkez/geri');

        $this->assertStringNotContainsString('/giris/merkez/geri', $this->politika());
    }

    /**
     * LİSTE ELLE TUTULMAZ.
     *
     * Yeni bir panel eklendiğinde politikanın kendiliğinden tazelenmesi
     * gerekiyor; aksi hâlde o panelin girişi, sebebi görünmeden çalışmazdı.
     */
    #[Test]
    public function yeni_uygulama_eklenince_politika_tazelenir(): void
    {
        $ilk = $this->politika();
        $this->assertStringNotContainsString('https://panel-c.ornek.test', $ilk);

        $this->istemciEkle('Satış', 'https://panel-c.ornek.test/giris/merkez/geri');

        $this->assertStringContainsString('https://panel-c.ornek.test', $this->politika());
    }

    /** Bağlantısı kesilmiş uygulama listeden düşer. */
    #[Test]
    public function iptal_edilen_uygulamanin_kokeni_dusulur(): void
    {
        $this->istemciEkle('Eski', 'https://eski.ornek.test/giris/merkez/geri');
        $this->assertStringContainsString('https://eski.ornek.test', $this->politika());

        OauthClient::query()->where('name', 'Eski')->first()->forceFill(['revoked' => true])->save();

        $this->assertStringNotContainsString('https://eski.ornek.test', $this->politika());
    }

    /**
     * VERİTABANI DÜŞSE DE BAŞLIK ÇIKAR.
     *
     * Liste her yanıtta okunuyor, hata sayfalarında da. Burada patlamak
     * "veritabanına ulaşılamıyor" sayfasını çıplak bir 500'e çevirirdi.
     */
    #[Test]
    public function veritabani_okunamazsa_politika_yine_uretilir(): void
    {
        ClientOrigins::unut();
        Schema::drop('oauth_clients');

        $csp = $this->politika();

        $this->assertStringNotContainsString('{istemciler}', $csp);
        $this->assertStringContainsString("form-action 'self';", $csp);
    }

    /** Yer tutucu başlığa sızmaz; hiç istemci yokken de politika geçerli kalır. */
    #[Test]
    public function istemci_yokken_politika_bozulmaz(): void
    {
        $csp = $this->politika();

        $this->assertStringNotContainsString('{istemciler}', $csp);
        $this->assertStringContainsString("form-action 'self';", $csp);
    }
}
