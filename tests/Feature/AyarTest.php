<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\IkiAdimZorunlu;
use App\Models\Ayar;
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
class AyarTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function varsayilan_olarak_zorunlu_degildir(): void
    {
        $this->assertFalse(Ayar::bool(IkiAdimZorunlu::YONETICI));

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('yonetim.sistemler'))
            ->assertOk();
    }

    #[Test]
    public function acildiginda_iki_adimsiz_yonetici_kuruluma_yonlendirilir(): void
    {
        Ayar::yaz(IkiAdimZorunlu::YONETICI, true);

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('yonetim.sistemler'))
            ->assertRedirect(route('hesap.iki-adim'));

        /* İkinci adımı kurmuş yönetici etkilenmez. */
        $this->actingAs(User::factory()->yonetici()->ikiAdimli()->create())
            ->get(route('yonetim.sistemler'))
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
        Ayar::yaz(IkiAdimZorunlu::YONETICI, true);

        $yetkili = User::factory()->rol('muhasebe')->create();

        $this->actingAs($yetkili)
            ->get(route('yonetim.kullanicilar'))
            ->assertRedirect(route('hesap.iki-adim'));

        /* Yönetim yetkisi OLMAYAN kullanıcı bu kuraldan etkilenmez. */
        $this->actingAs(User::factory()->rol('network')->create())
            ->get(route('hizli-erisim'))
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
            ->put(route('yonetim.ayarlar.kaydet'), ['iki_adim_zorunlu' => '1'])
            ->assertSessionHas('error');

        $this->assertFalse(Ayar::bool(IkiAdimZorunlu::YONETICI), 'Ayar açılmamalıydı.');
    }

    /**
     * Kullanıcı anahtarı AÇIKKEN kural yönetimle sınırlı değil.
     *
     * Giriş yapan herkes, hiçbir şey yapmadan önce kuruluma gönderilir.
     */
    #[Test]
    public function kullanici_anahtari_acikken_herkes_kuruluma_gonderilir(): void
    {
        Ayar::yaz(IkiAdimZorunlu::KULLANICI, true);

        $this->actingAs(User::factory()->create())
            ->get(route('hizli-erisim'))
            ->assertRedirect(route('hesap.iki-adim'));

        /* Kurulum sayfasının kendisi ve çıkış kapanmaz; yoksa kurulum yapılamazdı. */
        $this->actingAs(User::factory()->create())->get(route('hesap.iki-adim'))->assertOk();
    }

    #[Test]
    public function iki_adimli_yonetici_zorunlulugu_acabilir_ve_kapatabilir(): void
    {
        $yonetici = User::factory()->yonetici()->ikiAdimli()->create();

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.kaydet'), [
            'iki_adim_zorunlu' => '1',
            'iki_adim_zorunlu_kullanici' => '1',
        ]);
        $this->assertTrue(Ayar::bool(IkiAdimZorunlu::YONETICI));
        $this->assertTrue(Ayar::bool(IkiAdimZorunlu::KULLANICI));

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.kaydet'), []);
        $this->assertFalse(Ayar::bool(IkiAdimZorunlu::YONETICI));

        $this->assertDatabaseHas('activity_logs', ['eylem' => 'ayar-degisti']);
    }

    #[Test]
    public function siradan_kullanici_ayarlara_giremez(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('yonetim.ayarlar'))
            ->assertForbidden();
    }
}
