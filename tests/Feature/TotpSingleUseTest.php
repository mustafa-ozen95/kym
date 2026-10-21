<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Totp;
use App\Services\TwoStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bir TOTP kodu BİR KEZ geçer.
 *
 * NEDEN: kod 90 saniyeye kadar geçerli kalıyor (30 saniyelik adım artı iki
 * komşu) ve "geçerli mi" sorusunun cevabı o sürede değişmiyordu — aynı kod
 * ikinci kez de kabul ediliyordu. Omzunun üstünden kodu gören ya da araya
 * giren biri için bu, pencerenin sonuna kadar süren ikinci bir giriş hakkı
 * demekti; ikinci adımın var olma sebebi tam da bunu engellemek
 * (RFC 6238 §5.2).
 */
class TotpSingleUseTest extends TestCase
{
    use RefreshDatabase;

    private function hesap(): array
    {
        $totp = app(Totp::class);
        $secret = $totp->secret();

        $kullanici = User::factory()->create(['totp_secret' => $secret, 'totp_confirmed_at' => now()]);

        return [$kullanici, $totp, $secret];
    }

    #[Test]
    public function ayni_kod_ikinci_kez_kabul_edilmez(): void
    {
        [$kullanici, $totp, $secret] = $this->hesap();
        $ikiAdim = app(TwoStep::class);

        $kod = $totp->code($secret);

        $this->assertTrue($ikiAdim->dogrula($kullanici, $kod), 'Kod ilk kullanımda geçmeliydi.');
        $this->assertFalse($ikiAdim->dogrula($kullanici, $kod), 'Aynı kod ikinci kez geçti.');
    }

    /**
     * Damga KULLANICIYA ait: bir kişinin kodu harcaması başkasını kilitlemez.
     *
     * Aynı anda iki kişinin kodu eşit çıkabilir (altı hane, 10^6 ihtimal);
     * damga ortak olsaydı biri diğerinin girişini engellerdi.
     */
    #[Test]
    public function bir_kullanicinin_harcadigi_adim_otekini_etkilemez(): void
    {
        [$birinci, $totp, $secret] = $this->hesap();
        $ikinci = User::factory()->create(['totp_secret' => $secret, 'totp_confirmed_at' => now()]);

        $ikiAdim = app(TwoStep::class);
        $kod = $totp->code($secret);

        $this->assertTrue($ikiAdim->dogrula($birinci, $kod));
        $this->assertTrue($ikiAdim->dogrula($ikinci, $kod));
    }

    /** Kurtarma kodu yolu bozulmadı. */
    #[Test]
    public function kurtarma_kodu_hala_calisir_ve_bir_kez_gecer(): void
    {
        [$kullanici, $totp, $secret] = $this->hesap();
        $ikiAdim = app(TwoStep::class);

        $kodlar = $ikiAdim->onayla($kullanici->fresh(), $totp->code($secret));
        $this->assertNotNull($kodlar);

        $kullanici->refresh();

        $this->assertTrue($ikiAdim->dogrula($kullanici, $kodlar[0]));
        $this->assertFalse($ikiAdim->dogrula($kullanici->fresh(), $kodlar[0]));
    }
}
