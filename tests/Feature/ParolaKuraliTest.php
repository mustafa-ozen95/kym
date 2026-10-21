<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\User;
use App\Services\ParolaKurali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parolayı belirlemenin dört yolu VAR ve dördü aynı kuralı uygular.
 *
 * Aynı alanı yazan yollardan biri gevşek kalırsa (ör. kayıt harf ve rakam
 * isterken yönetici ekranı yalnız uzunluğa bakarsa) "12345678" bir kapıdan
 * geçmezken öbüründen geçer. Kural tek yerde (ParolaKurali) ve bu test
 * yolların ayrılmadığını kanıtlar.
 */
final class ParolaKuraliTest extends TestCase
{
    use RefreshDatabase;

    /** Rakamsız parola: hiçbir kapıdan geçmemeli. */
    public function test_kayit_rakamsiz_parolayi_reddeder(): void
    {
        $this->post(route('kayit'), [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@ornek.test',
            'password' => 'parolaparola',
            'password_confirmation' => 'parolaparola',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'yeni@ornek.test']);
    }

    /** Aynı parola yönetici eliyle de yazılamaz. */
    public function test_yonetici_ekrani_harfsiz_parolayi_reddeder(): void
    {
        $this->actingAs(User::factory()->rol('admin')->create(['durum' => Durum::Etkin]))
            ->post(route('yonetim.kullanicilar.ekle'), [
                'name' => 'Yeni Kisi',
                'email' => 'yeni@ornek.test',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'yeni@ornek.test']);
    }

    /** Kısa parola profilden de geçmez. */
    public function test_profil_kisa_parolayi_reddeder(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Durum::Etkin,
            'password' => Hash::make('eski-parola-1616'),
        ]);

        $this->actingAs($kullanici)
            ->put(route('hesap.parola'), [
                'current_password' => 'eski-parola-1616',
                'password' => 'kisa1',
                'password_confirmation' => 'kisa1',
            ])
            ->assertSessionHasErrors('password');
    }

    /** Kurallara uyan parola kabul edilir: kapı kapanmış olmasın. */
    public function test_uygun_parola_kabul_edilir(): void
    {
        $this->post(route('kayit'), [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@ornek.test',
            'password' => 'cok-gizli-1616',
            'password_confirmation' => 'cok-gizli-1616',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'yeni@ornek.test']);
    }

    /**
     * Ekrandaki ölçüt AYARLA birlikte döner.
     *
     * Metin elle yazılsaydı sınır değiştiğinde ekran eski sayıyı söylemeye
     * devam ederdi.
     */
    public function test_olcut_metni_ayardaki_uzunlugu_soyler(): void
    {
        config(['limits.parola_uzunluk' => 11]);

        $uzunluk = collect(ParolaKurali::olcutler())->firstWhere('anahtar', 'uzunluk');

        $this->assertSame('En az 11 karakter', $uzunluk['metin']);
    }

    /**
     * Sunucu mesajları TÜRKÇE.
     *
     * Dil ayarı geri alınırsa Laravel'in kendi metni İngilizce döner ve
     * Türkçe alan adlarıyla "The parola field must be at least 8
     * characters." gibi yarı Türkçe cümleler çıkar; bu test söyler.
     */
    public function test_dogrulama_mesajlari_turkcedir(): void
    {
        $this->post(route('kayit'), [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@ornek.test',
            'password' => 'ab1',
            'password_confirmation' => 'ab1',
        ])->assertSessionHasErrors([
            'password' => 'Parola en az '.ParolaKurali::uzunluk().' karakter olmalıdır.',
        ]);
    }

    /**
     * Kayıt ekranı kuralları TAŞIR.
     *
     * Metin ekranda sürekli durmaz — eksik çıkınca yazılır, kurallar "?"
     * iminde bekler — ama ikisinin de kaynağı sunucudur ve sayfada bulunur.
     */
    public function test_kayit_ekrani_kurallari_tasir(): void
    {
        $this->get(route('hizli-erisim', ['pencere' => 'kayit']))
            ->assertOk()
            ->assertSee('data-parola-olcut', escape: false)
            ->assertSee('ipucu-imi', escape: false)
            ->assertSee('En az '.ParolaKurali::uzunluk().' karakter');
    }
}
