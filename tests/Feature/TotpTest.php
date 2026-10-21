<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Totp;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TOTP hesabı.
 *
 * Kendi kodumuzu yazdık; bu yüzden doğruluğu iddia etmiyor, KANITLIYORUZ:
 * RFC 6238'in kendi test vektörleri kullanılıyor. Vektörlerdeki gizli anahtar
 * "12345678901234567890" ASCII dizisidir ve belirli anlarda üretmesi gereken
 * kodlar standartta yazılıdır.
 */
class TotpTest extends TestCase
{
    /** RFC 6238'deki anahtarın base32 karşılığı. */
    private const ANAHTAR = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    #[Test]
    public function rfc_test_vektorlerini_uretir(): void
    {
        $totp = new Totp;

        /* RFC 6238, Appendix B — SHA1 sütunu, son altı hane. */
        $vektorler = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vektorler as $an => $beklenen) {
            $this->assertSame($beklenen, $totp->code(self::ANAHTAR, $an), "t={$an} için kod tutmadı.");
        }
    }

    #[Test]
    public function dogru_kod_kabul_edilir(): void
    {
        $totp = new Totp;
        $an = 1111111111;

        $this->assertTrue($totp->verify(self::ANAHTAR, $totp->code(self::ANAHTAR, $an), $an));
    }

    /**
     * Saat kayması AFFEDİLİR ama sınırsız değil.
     *
     * Telefon saati birkaç saniye kayıksa kullanıcı kendini kilitli bulmamalı;
     * öte yandan pencere genişledikçe çalınmış bir kodun ömrü uzar.
     */
    #[Test]
    public function komsu_adimlar_kabul_edilir_uzagi_reddedilir(): void
    {
        $totp = new Totp;
        $an = 1111111111;

        $onceki = $totp->code(self::ANAHTAR, $an - 30);
        $sonraki = $totp->code(self::ANAHTAR, $an + 30);
        $uzak = $totp->code(self::ANAHTAR, $an + 120);

        $this->assertTrue($totp->verify(self::ANAHTAR, $onceki, $an), 'Bir önceki adım kabul edilmeli.');
        $this->assertTrue($totp->verify(self::ANAHTAR, $sonraki, $an), 'Bir sonraki adım kabul edilmeli.');
        $this->assertFalse($totp->verify(self::ANAHTAR, $uzak, $an), 'Dört adım öteki kod kabul edilmemeli.');
    }

    #[Test]
    public function bicimsiz_kod_reddedilir(): void
    {
        $totp = new Totp;

        foreach (['', '12345', '1234567', 'abcdef', '   '] as $kotu) {
            $this->assertFalse($totp->verify(self::ANAHTAR, $kotu), "Reddedilmeliydi: '{$kotu}'");
        }
    }

    /** Boşluk ve tire kullanıcı hatası değil; temizlenir. */
    #[Test]
    public function bosluklu_kod_temizlenip_kabul_edilir(): void
    {
        $totp = new Totp;
        $an = 1111111111;
        $kod = $totp->code(self::ANAHTAR, $an);

        $this->assertTrue($totp->verify(self::ANAHTAR, substr($kod, 0, 3).' '.substr($kod, 3), $an));
    }

    #[Test]
    public function uretilen_anahtar_gecerli_base32(): void
    {
        $totp = new Totp;
        $anahtar = $totp->secret();

        $this->assertSame(32, strlen($anahtar), '160 bit, base32 ile 32 karakter eder.');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $anahtar);
        $this->assertTrue($totp->verify($anahtar, $totp->code($anahtar)));
    }

    #[Test]
    public function kurtarma_kodlari_benzersizdir(): void
    {
        $kodlar = (new Totp)->recoveryCodes();

        $this->assertCount(10, $kodlar);
        $this->assertCount(10, array_unique($kodlar));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{6}-[0-9a-f]{6}$/', $kodlar[0]);
    }

    #[Test]
    public function uri_dogrulayici_uygulamalarin_bekledigi_bicimde(): void
    {
        $uri = (new Totp)->uri(self::ANAHTAR, 'admin@ornek.test', 'Kimlik Yönetim Merkezi');

        /*
         * Beklenen ad, ÜSTTE VERİLEN addır: sınanan şey adın kendisi değil,
         * URI'nin biçimi.
         */
        $this->assertStringStartsWith(
            'otpauth://totp/'.rawurlencode('Kimlik Yönetim Merkezi').':admin%40ornek.test?',
            $uri,
        );
        $this->assertStringContainsString('secret='.self::ANAHTAR, $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
