<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HSTS KAPALI OLMALI — ve `max-age=0` ile gönderilmeli.
 *
 * Başlık tarayıcıya "bu adrese bir daha asla http ile gitme" der ve bunu
 * kendi içinde saklar. Sertifika düşerse tarayıcı uyarı ekranını bile
 * göstermez: "yine de devam et" düğmesi
 * HSTS'li adreslerde yoktur ve panele hiçbir şekilde girilemez. Sertifika
 * düşünce panele erişim kesilmesin diye HSTS varsayılan olarak kapalı.
 *
 * BAŞLIĞI SİLMEK YETMEZ: tarayıcıda yerleşmiş kayıt bir yıl daha yaşar.
 * `max-age=0` onu sildirir — bu yüzden başlık duruyor ama sıfır.
 */
class HstsDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_hsts_sifir_gonderilir(): void
    {
        $yanit = $this->get(route('hizli-erisim', ['pencere' => 'giris']));

        $yanit->assertOk();
        $yanit->assertHeader('Strict-Transport-Security', 'max-age=0');
    }

    /** Ayar dosyasında da bir yıllık bir değer kalmamalı. */
    public function test_yapilandirmada_uzun_omur_yok(): void
    {
        $this->assertSame('max-age=0', config('security.headers.Strict-Transport-Security'));
    }
}
