<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Sistem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hızlı Erişim sayfası.
 *
 * Sayfa eskiden statikti ve sistem listesi çizim betiğinin içinde sabit bir
 * diziydi: bir kutucuk eklemek kod düzenlemek demekti. Artık liste kayıt;
 * bu testler o taşımanın kilididir.
 */
class HizliErisimTest extends TestCase
{
    use RefreshDatabase;

    private function sistem(array $ustune = []): Sistem
    {
        return Sistem::create([
            'ad' => 'Örnek Sistem',
            'aciklama' => 'Açıklama',
            'adres' => 'https://ornek.test',
            'kod' => 'ORN-01',
            'renk' => '#79c8a4',
            'grup' => 'ic',
            'serit' => 's1',
            'sira' => 10,
            ...$ustune,
        ]);
    }

    #[Test]
    public function sayfa_giris_istemeden_acilir(): void
    {
        /* Bilinçli karar: sistemleri görmek için giriş şart değil. */
        $this->get('/')->assertOk()->assertSee('Hangi sisteme gidiyorsun?');
    }

    /** Liste sayfaya veri olarak gömülür; çizim betiği onu okur. */
    #[Test]
    public function sistemler_sayfaya_veri_olarak_gecer(): void
    {
        $this->sistem(['ad' => 'CRM', 'adres' => 'https://crm.test']);

        $govde = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="sistem-verisi"', $govde);
        $this->assertStringContainsString('CRM', $govde);
        $this->assertStringContainsString('https:\/\/crm.test', $govde);
    }

    /**
     * Kapalı kayıt sayfaya HİÇ gitmez.
     *
     * "Gönder ama gizle" bir çözüm değil: kaynağa bakan onu yine görür.
     */
    #[Test]
    public function etkin_olmayan_sistem_sayfaya_gitmez(): void
    {
        $this->sistem(['ad' => 'Gizli Sistem', 'kod' => 'GIZ-01', 'etkin' => false]);

        $this->get('/')->assertOk()->assertDontSee('Gizli Sistem');
    }

    #[Test]
    public function sistemler_sira_alanina_gore_dizilir(): void
    {
        $this->sistem(['ad' => 'Sonraki', 'kod' => 'SON-01', 'sira' => 20]);
        $this->sistem(['ad' => 'Onceki', 'kod' => 'ONC-01', 'sira' => 10]);

        $govde = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($govde, 'Sonraki'),
            strpos($govde, 'Onceki'),
            'Küçük sıra numarası önce gelmeli.',
        );
    }

    /**
     * Kabin kimliği çizimdeki degrade tanımlarını benzersiz kılar.
     *
     * Kayıtta yoksa kaydın kendisinden türetilir; yoksa yeni eklenen bir
     * sistem başka bir kutucuğun degradesini ezerdi.
     *
     * GEÇERLİ bir çizime düşmeli: var olmayan bir çizim adı çizim betiğini
     * durdurur ve arkasından gelen her şey (kalan kutucuklar, tema düğmesi,
     * hesap menüsü) kurulmaz.
     */
    #[Test]
    public function kabin_kimligi_bos_kayitta_gecerli_bir_cizime_duser(): void
    {
        $sistem = $this->sistem(['kabin' => null]);

        /*
         * Türetme UUID'den yapılır: sıralı anahtardan türeyen değer dışarıya
         * kaydın kaçıncı sırada açıldığını sızdırırdı.
         */
        $beklenen = 'k'.((crc32((string) $sistem->uuid) % 13) + 1);

        $this->get('/')->assertOk()->assertSee($beklenen);
        $this->assertMatchesRegularExpression('/^k([1-9]|1[0-3])$/', $beklenen);
    }

    /**
     * KENDİ SAYFASI OLAN SALONDA DÖNÜŞ BAĞLANTISI DURUR.
     *
     * O salon ana listede görünmez; bağlantı olmadan kullanıcı tarayıcının
     * geri düğmesine mahkûm kalır ve kutucuğa yeni sekmede gelmişse o da
     * işe yaramaz. Ana sayfada ise çizilmez: dönülecek bir yer yok.
     */
    #[Test]
    public function salon_sayfasinda_ana_sayfaya_donus_baglantisi_vardir(): void
    {
        $salon = Grup::create(['ad' => 'Salon A', 'anahtar' => 'salon-a', 'ayri_sayfa' => true]);

        $this->get(route('salon', $salon->anahtar))
            ->assertOk()
            ->assertSee('class="baglanti geri"', false)
            ->assertSee(route('hizli-erisim'), false);

        $this->get(route('hizli-erisim'))
            ->assertOk()
            ->assertDontSee('class="baglanti geri"', false);
    }
}
