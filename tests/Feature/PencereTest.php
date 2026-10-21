<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pencerenin ADRESİ.
 *
 * Giriş ve kayıt pencere içinde açılır ama pencere yalnızca betiğin açtığı
 * bir kutu değildir: kendi adresi vardır ve sunucu onu açık çizebilir.
 * Bu üç şeyi birden mümkün kılar — doğrudan bağlantı, doğrulama hatasında
 * pencerenin açık geri gelmesi, ve betik yüklenmediğinde çalışmaya devam.
 */
class PencereTest extends TestCase
{
    use RefreshDatabase;

    /** Pencere KAPALI gelir: sayfa normalde kutucukları göstermek içindir. */
    #[Test]
    public function pencere_varsayilan_olarak_kapali_gelir(): void
    {
        $govde = $this->get(route('hizli-erisim'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="pencere-giris"[^>]*hidden/', $govde);
    }

    /** Adresle gelindiğinde SUNUCU pencereyi açık çizer; betik gerekmez. */
    #[Test]
    public function adresle_gelince_pencere_acik_cizilir(): void
    {
        $govde = $this->get(route('hizli-erisim', ['pencere' => 'giris']))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="pencere-giris"[^>]*hidden/', $govde);
        $this->assertMatchesRegularExpression('/id="pencere-kayit"[^>]*hidden/', $govde, 'Yalnızca istenen pencere açılmalı.');
    }

    #[Test]
    public function kayit_penceresi_de_adresle_acilir(): void
    {
        $govde = $this->get(route('hizli-erisim', ['pencere' => 'kayit']))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="pencere-kayit"[^>]*hidden/', $govde);
    }

    /** Tanınmayan değer sessizce yok sayılır; ekran bozulmaz. */
    #[Test]
    public function taninmayan_pencere_adi_yok_sayilir(): void
    {
        $govde = $this->get(route('hizli-erisim', ['pencere' => 'olmayan']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="pencere-giris"[^>]*hidden/', $govde);
    }

    /**
     * Doğrulama hatasında pencere AÇIK geri gelir.
     *
     * Form kendi adresine döndüğü için sunucu onu yeniden açık çizer;
     * kullanıcı yazdıklarını kaybetmez.
     */
    #[Test]
    public function dogrulama_hatasinda_pencere_acik_geri_gelir(): void
    {
        $yanit = $this->from(route('hizli-erisim', ['pencere' => 'kayit']))
            ->post(route('kayit'), ['name' => '', 'email' => 'gecersiz', 'password' => 'kisa']);

        $yanit->assertRedirect(route('hizli-erisim', ['pencere' => 'kayit']));

        $govde = $this->followingRedirects()
            ->from(route('hizli-erisim', ['pencere' => 'kayit']))
            ->post(route('kayit'), ['name' => '', 'email' => 'gecersiz', 'password' => 'kisa'])
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="pencere-kayit"[^>]*hidden/', $govde);
    }

    /** Oturum açıkken giriş penceresi sayfada HİÇ bulunmaz. */
    #[Test]
    public function oturum_acikken_pencereler_cizilmez(): void
    {
        $govde = $this->actingAs(User::factory()->create())
            ->get(route('hizli-erisim', ['pencere' => 'giris']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="pencere-giris"', $govde);
        $this->assertStringContainsString('Çıkış', $govde);
    }

    /**
     * Başlıkta yalnızca "Giriş" durur.
     *
     * Kayıt, giriş penceresinin içinden açılıyor; iki bağlantıyı yan yana
     * koymak başlığı kalabalıklaştırıyordu.
     */
    #[Test]
    public function baslikta_yalnizca_giris_baglantisi_bulunur(): void
    {
        $govde = $this->get(route('hizli-erisim'))->assertOk()->getContent();

        $baslik = substr($govde, (int) strpos($govde, '<div class="head">'), 1200);

        $this->assertStringContainsString('data-pencere-ac="giris"', $baslik);
        $this->assertStringNotContainsString('data-pencere-ac="kayit"', $baslik);

        /* Kayıt yolu kapanmadı: giriş penceresinin içinden açılıyor. */
        $this->assertStringContainsString('data-pencere-ac="kayit"', $govde);
    }

    /**
     * KAYDEDİNCE PENCERE KAPANIR.
     *
     * Pencerenin adresi var (?pencere=duzenle&kayit=...) ve ekran o adreste
     * pencereyi kendiliğinden açıyor. Kaydeden istek back() ile aynı adrese
     * dönseydi pencere hemen yeniden açılırdı: kullanıcı "kaydet"e bastığında
     * hiçbir şey olmamış gibi görünürdü.
     */
    #[Test]
    public function kullanici_kaydedilince_pencere_kapanir(): void
    {
        $yonetici = User::factory()->rol('admin')->create(['durum' => Durum::Etkin]);
        $hedef = User::factory()->create(['name' => 'Eski Ad']);

        $adres = route('yonetim.kullanicilar', ['pencere' => 'duzenle', 'kayit' => $hedef->uuid]);

        $this->actingAs($yonetici)
            ->from($adres)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => 'Yeni Ad',
                'email' => $hedef->email,
            ])
            /* Pencereyi açan sorgu parçaları adreste KALMAZ. */
            ->assertRedirect(route('yonetim.kullanicilar'));

        $this->assertSame('Yeni Ad', $hedef->fresh()->name);
    }

    /**
     * Doğrulama hatasında ise pencere AÇIK geri gelmeli: kullanıcı formu
     * yeniden doldurmak zorunda kalmamalı.
     */
    #[Test]
    public function kullanici_dogrulama_hatasinda_pencere_acik_geri_gelir(): void
    {
        $yonetici = User::factory()->rol('admin')->create(['durum' => Durum::Etkin]);
        $hedef = User::factory()->create();

        $adres = route('yonetim.kullanicilar', ['pencere' => 'duzenle', 'kayit' => $hedef->uuid]);

        $this->actingAs($yonetici)
            ->from($adres)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['name' => '', 'email' => 'gecersiz'])
            ->assertRedirect($adres)
            ->assertSessionHasErrors('email');
    }
}
