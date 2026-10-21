<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KullaniciYetkisi;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yetki dağıtımı AÇIKKEN (yonetim_yalnizca_yonetici=false) yönetici
 * olmayanın yönetici hesabına ya da yönetici bayrağına uzanamaması.
 *
 * Varsayılan kurulumda bu yol zaten kapalı; testler kapıyı bilerek açıp
 * ikinci savunmanın yerinde durduğunu kilitliyor.
 */
final class YetkiYukseltmeTest extends TestCase
{
    use RefreshDatabase;

    private function yetkili(string ...$yetenekler): User
    {
        $this->yetkiDagitimiAc();

        $kisi = User::factory()->rol('muhasebe')->create();

        foreach ($yetenekler as $yetenek) {
            KullaniciYetkisi::create(['user_id' => $kisi->id, 'yetenek' => $yetenek, 'etki' => KullaniciYetkisi::IZIN]);
        }

        return $kisi;
    }

    public function test_duzenle_yetkisi_yonetici_hesabinin_parolasini_degistiremez(): void
    {
        $saldirgan = $this->yetkili('kullanici.gor', 'kullanici.duzenle');
        $yonetici = User::factory()->yonetici()->create(['email' => 'yonetici@ornek.test']);
        $eskiParola = $yonetici->password;

        $this->actingAs($saldirgan)
            ->put(route('yonetim.kullanicilar.guncelle', $yonetici), [
                'name' => $yonetici->name,
                'email' => 'ele-gecirildi@ornek.test',
                'password' => 'yeni-parola-9999',
                'password_confirmation' => 'yeni-parola-9999',
            ])
            ->assertForbidden();

        $yonetici->refresh();
        $this->assertSame('yonetici@ornek.test', $yonetici->email);
        $this->assertSame($eskiParola, $yonetici->password);
    }

    public function test_yonetici_baska_yoneticiyi_duzenleyebilir(): void
    {
        $this->yetkiDagitimiAc();
        $ben = User::factory()->yonetici()->create();
        $hedef = User::factory()->yonetici()->create();

        $this->actingAs($ben)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['name' => 'Yeni Ad', 'email' => $hedef->email])
            ->assertRedirect();

        $this->assertSame('Yeni Ad', $hedef->fresh()->name);
    }

    public function test_rol_atama_yetkisi_yonetici_rolunu_vermez(): void
    {
        $saldirgan = $this->yetkili('kullanici.gor', 'kullanici.rol');
        $hedef = User::factory()->rol('muhasebe')->create();
        $yoneticiRol = Rol::where('is_admin', true)->firstOrFail();

        $this->actingAs($saldirgan)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['rol' => $yoneticiRol->uuid])
            ->assertForbidden();

        $this->assertFalse($hedef->fresh()->yonetici());
    }

    public function test_ekleme_yetkisi_yonetici_rolunde_hesap_acamaz(): void
    {
        $saldirgan = $this->yetkili('kullanici.gor', 'kullanici.ekle', 'kullanici.rol');
        $yoneticiRol = Rol::where('is_admin', true)->firstOrFail();

        $this->actingAs($saldirgan)
            ->post(route('yonetim.kullanicilar.ekle'), [
                'name' => 'Sahte', 'email' => 'sahte@ornek.test',
                'password' => 'cok-gizli-1616', 'password_confirmation' => 'cok-gizli-1616',
                'rol' => $yoneticiRol->uuid,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sahte@ornek.test']);
    }

    public function test_rol_duzenle_yetkisi_kendi_rolunu_yonetici_yapamaz(): void
    {
        $saldirgan = $this->yetkili('rol.gor', 'rol.duzenle');
        $rol = $saldirgan->rol;

        $this->actingAs($saldirgan)
            ->put(route('yonetim.roller.guncelle', $rol), [
                'slug' => $rol->slug, 'label' => $rol->label, 'badge' => $rol->badge->value,
                'is_admin' => 1, 'template' => [],
            ])
            ->assertForbidden();

        $this->assertFalse($rol->fresh()->is_admin);
        $this->assertFalse($saldirgan->fresh()->yonetici());
    }

    public function test_rol_duzenle_yetkisi_yonetici_rolunu_duzenleyemez(): void
    {
        $saldirgan = $this->yetkili('rol.gor', 'rol.duzenle');
        $yoneticiRol = Rol::where('is_admin', true)->firstOrFail();

        $this->actingAs($saldirgan)
            ->put(route('yonetim.roller.guncelle', $yoneticiRol), [
                'slug' => $yoneticiRol->slug, 'label' => 'Ele geçti', 'badge' => $yoneticiRol->badge->value,
                'is_admin' => 1, 'is_default' => 1, 'template' => [],
            ])
            ->assertForbidden();

        $this->assertNotSame('Ele geçti', $yoneticiRol->fresh()->label);
    }

    public function test_rol_duzenle_yetkisi_yonetici_bayrakli_rol_acamaz(): void
    {
        $saldirgan = $this->yetkili('rol.gor', 'rol.duzenle');

        $this->actingAs($saldirgan)
            ->post(route('yonetim.roller.ekle'), [
                'slug' => 'sahte', 'label' => 'Sahte', 'badge' => 'green', 'is_admin' => 1, 'template' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roller', ['slug' => 'sahte']);
    }

    public function test_yonetici_rolu_yonetici_bayragi_verebilir(): void
    {
        $this->yetkiDagitimiAc();
        $ben = User::factory()->yonetici()->create();
        $rol = Rol::where('slug', 'muhasebe')->firstOrFail();

        $this->actingAs($ben)
            ->put(route('yonetim.roller.guncelle', $rol), [
                'slug' => $rol->slug, 'label' => $rol->label, 'badge' => $rol->badge->value,
                'is_admin' => 1, 'template' => [],
            ])
            ->assertRedirect();

        $this->assertTrue($rol->fresh()->is_admin);
    }
}
