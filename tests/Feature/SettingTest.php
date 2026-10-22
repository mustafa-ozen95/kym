<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\TwoStepRequired;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * İkinci adım zorunluluğu — ayardan açılır.
 *
 * Varsayılan KAPALI. Açıldığında kural hemen
 * geçerli olur; ekrandan değiştirilebilmesi bilinçli, çünkü .env düzenlemek
 * sunucu erişimi ister ve değişikliği kimin yaptığı belli olmaz.
 */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function varsayilan_olarak_zorunlu_degildir(): void
    {
        $this->assertFalse(Setting::bool(TwoStepRequired::ADMIN));

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('admin.systems'))
            ->assertOk();
    }

    #[Test]
    public function acildiginda_iki_adimsiz_yonetici_kuruluma_yonlendirilir(): void
    {
        Setting::yaz(TwoStepRequired::ADMIN, true);

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('admin.systems'))
            ->assertRedirect(route('account.two-step'));

        /* İkinci adımı kurmuş yönetici etkilenmez. */
        $this->actingAs(User::factory()->yonetici()->ikiAdimli()->create())
            ->get(route('admin.systems'))
            ->assertOk();
    }

    /**
     * Yönetim kapısından (can:yonet) geçen herkes kurala tabidir; yalnızca
     * is_admin rolü değil. Yetki dağıtımı açıkken yönetim yetkisi verilmiş
     * yönetici olmayan biri ikinci adımsız yönetim ekranlarında kalamaz.
     */
    #[Test]
    public function acildiginda_yetki_verilmis_yonetici_olmayan_da_kuruluma_yonlendirilir(): void
    {
        $this->yetkiDagitimiAc('muhasebe', ['kullanici.gor']);
        Setting::yaz(TwoStepRequired::ADMIN, true);

        $yetkili = User::factory()->rol('muhasebe')->create();

        $this->actingAs($yetkili)
            ->get(route('admin.users'))
            ->assertRedirect(route('account.two-step'));

        /* Yönetim yetkisi OLMAYAN kullanıcı bu kuraldan etkilenmez. */
        $this->actingAs(User::factory()->rol('network')->create())
            ->get(route('quick-access'))
            ->assertOk();
    }

    /**
     * Kendini KİLİTLEYEMEZSİN.
     *
     * Zorunluluğu açan yöneticinin kendi ikinci adımı yoksa, kaydettiği anda
     * yönetimden düşer ve geri açacak kimse kalmayabilir.
     */
    #[Test]
    public function kendi_iki_adimi_olmayan_yonetici_zorunlulugu_acamaz(): void
    {
        $yonetici = User::factory()->yonetici()->create();

        $this->actingAs($yonetici)
            ->put(route('admin.settings.update'), ['two_step_required_admins' => '1'])
            ->assertSessionHas('error');

        $this->assertFalse(Setting::bool(TwoStepRequired::ADMIN), 'Ayar açılmamalıydı.');
    }

    /**
     * Kullanıcı anahtarı AÇIKKEN kural yönetimle sınırlı değil.
     *
     * Giriş yapan herkes, hiçbir şey yapmadan önce kuruluma gönderilir.
     */
    #[Test]
    public function kullanici_anahtari_acikken_herkes_kuruluma_gonderilir(): void
    {
        Setting::yaz(TwoStepRequired::USER, true);

        $this->actingAs(User::factory()->create())
            ->get(route('quick-access'))
            ->assertRedirect(route('account.two-step'));

        /* Kurulum sayfasının kendisi ve çıkış kapanmaz; yoksa kurulum yapılamazdı. */
        $this->actingAs(User::factory()->create())->get(route('account.two-step'))->assertOk();
    }

    #[Test]
    public function iki_adimli_yonetici_zorunlulugu_acabilir_ve_kapatabilir(): void
    {
        $yonetici = User::factory()->yonetici()->ikiAdimli()->create();

        $this->actingAs($yonetici)->put(route('admin.settings.update'), [
            'two_step_required_admins' => '1',
            'two_step_required_users' => '1',
        ]);
        $this->assertTrue(Setting::bool(TwoStepRequired::ADMIN));
        $this->assertTrue(Setting::bool(TwoStepRequired::USER));

        $this->actingAs($yonetici)->put(route('admin.settings.update'), []);
        $this->assertFalse(Setting::bool(TwoStepRequired::ADMIN));

        $this->assertDatabaseHas('activity_logs', ['eylem' => 'ayar-degisti']);
    }

    #[Test]
    public function siradan_kullanici_ayarlara_giremez(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings'))
            ->assertForbidden();
    }
}
