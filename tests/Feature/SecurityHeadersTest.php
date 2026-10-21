<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tarayıcıya gönderilen güvenlik başlıkları.
 *
 * Burası kimlik
 * sağlayıcısı: çerçeveye alınmış bir giriş ekranı, kullanıcının parolayı
 * hangi siteye yazdığını göremediği bir giriş ekranıdır.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function giris_sayfasi_cerceveye_alinamaz(): void
    {
        $yanit = $this->get(route('hizli-erisim', ['pencere' => 'giris']));

        $yanit->assertOk();
        $yanit->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $csp = (string) $yanit->headers->get('Content-Security-Policy');

        /* Satır içi script ve style YOK; politikanın anlamı buradan geliyor. */
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $yanit->assertHeader('X-Content-Type-Options', 'nosniff');
        $yanit->assertHeader('Referrer-Policy', 'same-origin');
    }

    /**
     * OTURUMSUZ İSTEĞİN YÖNLENDİRMESİ DE BAŞLIK TAŞIR.
     *
     * Ara katman "web" grubunda en içte kalsaydı, kimlik doğrulaması düşünce
     * yanıtı istisna işleyicisi kurar ve o yanıt bir daha ara katmandan
     * geçmezdi: giriş yönlendirmesi ve hata sayfaları çıplak kalırdı.
     * Bu yüzden genel yığında.
     */
    #[Test]
    public function oturumsuz_istegin_yonlendirmesi_de_baslik_tasir(): void
    {
        $yanit = $this->get(route('hesap.profil'));

        $yanit->assertRedirect();
        $yanit->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $yanit->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
