<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kullanıcının kendi hesabı.
 *
 * Herkes yalnızca KENDİ hesabını düzenler; rol ve durum bu ekranın işi
 * değildir. "Kendi profilini düzenle" yetkisi, kendini yönetici yapma ya da
 * askıdan çıkma yetkisi değil.
 */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private function kullanici(array $ustune = []): User
    {
        return User::factory()->create([
            'name' => 'Deneme Kişi',
            'email' => 'kisi@ornek.test',
            'password' => Hash::make('parola-1234'),
            ...$ustune,
        ]);
    }

    #[Test]
    public function profil_oturumsuz_acilmaz(): void
    {
        $this->get(route('hesap.profil'))->assertRedirect(route('hizli-erisim', ['pencere' => 'giris']));
    }

    #[Test]
    public function kullanici_adini_ve_epostasini_degistirir(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)
            ->put(route('hesap.profil.kaydet'), ['name' => 'Yeni Ad', 'email' => 'yeni@ornek.test'])
            ->assertSessionHas('status');

        $kullanici->refresh();

        $this->assertSame('Yeni Ad', $kullanici->name);
        $this->assertSame('yeni@ornek.test', $kullanici->email);

        /* E-posta kimliğin kendisi: değişikliği kayda geçmeli. */
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'eposta-degisti']);
    }

    #[Test]
    public function baskasinin_epostasi_alinamaz(): void
    {
        $this->kullanici(['email' => 'dolu@ornek.test']);
        $kullanici = $this->kullanici(['email' => 'benim@ornek.test']);

        $this->actingAs($kullanici)
            ->put(route('hesap.profil.kaydet'), ['name' => 'Ad', 'email' => 'dolu@ornek.test'])
            ->assertSessionHasErrors('email');
    }

    /**
     * ROL VE DURUM bu ekrandan değişmez.
     *
     * Formda alanları yok; gönderilse bile yok sayılmalı. Aksi hâlde herkes
     * kendini yönetici yapabilirdi.
     */
    #[Test]
    public function profilden_rol_ve_durum_degistirilemez(): void
    {
        $kullanici = $this->kullanici(['durum' => Durum::Etkin]);

        $this->actingAs($kullanici)->put(route('hesap.profil.kaydet'), [
            'name' => 'Ad',
            'email' => 'kisi@ornek.test',
            'rol' => Rol::firstWhere('is_admin', true)->uuid,
            'durum' => Durum::Askida->value,
        ]);

        $kullanici->refresh();

        $this->assertFalse($kullanici->rol->is_admin);
        $this->assertSame(Durum::Etkin, $kullanici->durum);
    }

    /** Parola değiştirmek MEVCUT parolayı ister. */
    #[Test]
    public function parola_degistirmek_mevcut_parolayi_ister(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)->put(route('hesap.parola'), [
            'mevcut' => 'yanlis-parola',
            'password' => 'yeni-parola-9',
            'password_confirmation' => 'yeni-parola-9',
        ])->assertSessionHasErrors('mevcut');

        $this->assertTrue(Hash::check('parola-1234', $kullanici->fresh()->password));
    }

    #[Test]
    public function dogru_mevcut_parolayla_degisir(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)->put(route('hesap.parola'), [
            'mevcut' => 'parola-1234',
            'password' => 'yeni-parola-9',
            'password_confirmation' => 'yeni-parola-9',
        ])->assertSessionHas('status');

        $this->assertTrue(Hash::check('yeni-parola-9', $kullanici->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'parola-degisti']);
    }

    #[Test]
    public function yeni_parola_tekrariyla_eslesmeli_ve_kurala_uymali(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)->put(route('hesap.parola'), [
            'mevcut' => 'parola-1234',
            'password' => 'yeni-parola-9',
            'password_confirmation' => 'baska-parola',
        ])->assertSessionHasErrors('password');

        /* Kısa parola da reddedilir; sınır ayardan gelir. */
        $this->actingAs($kullanici)->put(route('hesap.parola'), [
            'mevcut' => 'parola-1234',
            'password' => 'kisa1',
            'password_confirmation' => 'kisa1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('parola-1234', $kullanici->fresh()->password));
    }

    /** Hesabım ekranı ikinci adıma da yol gösterir. */
    #[Test]
    public function profil_ikinci_adima_baglanti_verir(): void
    {
        $this->actingAs($this->kullanici())
            ->get(route('hesap.profil'))
            ->assertOk()
            ->assertSee(route('hesap.iki-adim'), escape: false);
    }
}
