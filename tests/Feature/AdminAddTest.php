<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * İlk yönetici konsoldan açılır.
 *
 * Panelde "kendini yönetici yap" yolu yok; zinciri başlatan tek yer burası.
 */
class AdminAddTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function yonetici_etkin_olarak_acilir(): void
    {
        $this->artisan('kimlik:yonetici-ekle', [
            '--ad' => 'Sistem Yöneticisi',
            '--eposta' => 'yonetici@ornek.test',
            '--parola' => 'cok-gizli-1234',
        ])->assertSuccessful();

        $kullanici = User::where('email', 'yonetici@ornek.test')->firstOrFail();

        $this->assertTrue($kullanici->rol->is_admin);
        $this->assertSame(Status::Etkin, $kullanici->durum, 'Konsoldan açılan yönetici onay beklemez.');
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'yonetici-atandi']);
    }

    /** Zayıf parola reddedilir; merkez bütün sistemlerin kapısı. */
    #[Test]
    public function zayif_parola_kabul_edilmez(): void
    {
        $this->artisan('kimlik:yonetici-ekle', [
            '--ad' => 'Kısa Parola',
            '--eposta' => 'kisa@ornek.test',
            '--parola' => '1234',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'kisa@ornek.test']);
    }

    /** Var olan hesabı yükseltirken parola verilmezse dokunulmaz. */
    #[Test]
    public function mevcut_hesap_parolasina_dokunmadan_yukseltilir(): void
    {
        $kullanici = User::factory()->beklemede()->create(['email' => 'mevcut@ornek.test']);
        $parola = $kullanici->password;

        $this->artisan('kimlik:yonetici-ekle', ['--ad' => 'Mevcut', '--eposta' => 'mevcut@ornek.test'])
            ->assertSuccessful();

        $kullanici->refresh();

        $this->assertTrue($kullanici->rol->is_admin);
        $this->assertSame(Status::Etkin, $kullanici->durum);
        $this->assertSame($parola, $kullanici->password, 'Parola verilmediyse değişmemeli.');
    }
}
