<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\User;
use Database\Seeders\IlkYoneticiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KURULUMUN İLK HESABI.
 *
 * Konteyner açılışında, kullanıcı tablosu boşken çağrılır. Buradaki üç şey
 * yanlış olursa kurulum yapan kişi kendi merkezine giremez: rolün yönetici
 * olması, hesabın etkin doğması ve parolanın ortamdan gelmesi.
 */
final class IlkYoneticiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_yonetici_etkin_ve_dogrulanmis_dogar(): void
    {
        config(['app.admin_email' => 'kurulum@ornek.test', 'app.admin_password' => 'kurulum-parolasi-16']);

        $this->seed(IlkYoneticiSeeder::class);

        $yonetici = User::where('email', 'kurulum@ornek.test')->firstOrFail();

        $this->assertTrue($yonetici->rol->is_admin, 'İlk hesap yönetici rolünde olmalı.');
        $this->assertSame(Durum::Etkin, $yonetici->durum);
        $this->assertNotNull($yonetici->email_verified_at);
    }

    /** Parola KODA GÖMÜLÜ DEĞİL: verilen parolayla giriş yapılabilmeli. */
    public function test_verilen_parola_ile_giris_yapilir(): void
    {
        config(['app.admin_email' => 'kurulum@ornek.test', 'app.admin_password' => 'kurulum-parolasi-16']);

        $this->seed(IlkYoneticiSeeder::class);

        $this->assertTrue(
            auth()->attempt(['email' => 'kurulum@ornek.test', 'password' => 'kurulum-parolasi-16']),
        );
    }

    /**
     * İkinci kez koşması var olan hesabı ÇOĞALTMAZ.
     *
     * Başlatıcı yalnızca tablo boşken çağırıyor ama konteyner yeniden
     * başlatıldığında bu koşulun bozulması sessiz bir ikinci yönetici
     * doğurabilirdi.
     */
    public function test_ikinci_kosu_yeni_hesap_acmaz(): void
    {
        config(['app.admin_email' => 'kurulum@ornek.test', 'app.admin_password' => 'kurulum-parolasi-16']);

        $this->seed(IlkYoneticiSeeder::class);
        $this->seed(IlkYoneticiSeeder::class);

        $this->assertSame(1, User::where('email', 'kurulum@ornek.test')->count());
    }
}
