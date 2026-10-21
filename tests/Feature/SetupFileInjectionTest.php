<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\OauthClient;
use App\Models\User;
use App\Services\Identity\SetupFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Üretilen KimlikMerkezi.php PHP KODUDUR ve panelin içinde çalışır; uygulama
 * adı ve dönüş adresi yöneticinin yazdığı metindir. Metin hiçbir yolla koda
 * dönüşmemeli: ne yorumdan çıkabilmeli ne dize sabitinden.
 */
final class SetupFileInjectionTest extends TestCase
{
    use RefreshDatabase;

    private const ZEHIRLI_AD = "*/ \$GLOBALS['kym_probe']='X'; /*";

    private const ZEHIRLI_ADRES = "https://x.test/a'.\$GLOBALS['y']='Z'.'";

    private function admin(): User
    {
        return User::factory()->rol('admin')->create(['durum' => Status::Etkin]);
    }

    private function istemci(string $ad, string $adres): OauthClient
    {
        /** @var OauthClient $istemci */
        $istemci = app(ClientRepository::class)->createAuthorizationCodeGrantClient($ad, [$adres]);

        return $istemci;
    }

    /** Dosyadaki kod belirteçlerinden değişken adlarını döker (yorum ve dize hariç). */
    private function degiskenler(string $icerik): array
    {
        $adlar = [];

        foreach (token_get_all($icerik, TOKEN_PARSE) as $belirtec) {
            if (is_array($belirtec) && $belirtec[0] === T_VARIABLE) {
                $adlar[] = $belirtec[1];
            }
        }

        return $adlar;
    }

    public function test_zehirli_ad_ve_adres_kod_olmaz_veri_kalir(): void
    {
        /* Veri tabanında zaten duran (eski) kayıt bile koda dönüşmemeli. */
        $istemci = $this->istemci(self::ZEHIRLI_AD, self::ZEHIRLI_ADRES);

        $icerik = app(SetupFile::class)->icerik($istemci);

        $this->assertNotContains('$GLOBALS', $this->degiskenler($icerik));

        $sabitler = [];
        foreach (token_get_all($icerik) as $belirtec) {
            if (is_array($belirtec) && $belirtec[0] === T_CONSTANT_ENCAPSED_STRING) {
                $sabitler[] = $belirtec[1];
            }
        }

        $this->assertContains(var_export(self::ZEHIRLI_AD, true), $sabitler);
        $this->assertContains(var_export(self::ZEHIRLI_ADRES, true), $sabitler);
    }

    public function test_ad_yorum_satirina_hic_girmez(): void
    {
        $icerik = app(SetupFile::class)->icerik($this->istemci('Ozel Panel Adi', 'https://a.test/geri'));

        $yorumlar = '';
        foreach (token_get_all($icerik) as $belirtec) {
            if (is_array($belirtec) && in_array($belirtec[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $yorumlar .= $belirtec[1];
            }
        }

        $this->assertStringNotContainsString('Ozel Panel Adi', $yorumlar);
    }

    public function test_uretilen_dosya_php_olarak_cozumlenir(): void
    {
        $icerik = app(SetupFile::class)->icerik($this->istemci("O'Brien \\ Panel", 'https://a.test/geri'));

        token_get_all($icerik, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function zehirliAdlar(): array
    {
        return [
            'yorum kapatan' => ['x */ phpinfo(); /*'],
            'cift tirnak' => ['x"y'],
            'tek tirnak' => ["x'y"],
            'satir sonu' => ["x\ny"],
            'ters bolu' => ['x\\y'],
        ];
    }

    #[DataProvider('zehirliAdlar')]
    public function test_istemci_adinda_tehlikeli_karakter_reddedilir(string $ad): void
    {
        $this->actingAs($this->admin())
            ->post(route('yonetim.istemciler.ekle'), ['name' => $ad, 'adresler' => 'https://yeni.test/geri'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('oauth_clients', 0);
    }

    public function test_istemci_adi_guncellemede_de_reddedilir(): void
    {
        $istemci = $this->istemci('Temiz', 'https://a.test/geri');

        $this->actingAs($this->admin())
            ->put(route('yonetim.istemciler.guncelle', $istemci), [
                'name' => self::ZEHIRLI_AD, 'adresler' => 'https://a.test/geri',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('Temiz', $istemci->fresh()->name);
    }

    public function test_adreste_tirnak_reddedilir(): void
    {
        $this->actingAs($this->admin())
            ->post(route('yonetim.istemciler.ekle'), ['name' => 'Panel', 'adresler' => self::ZEHIRLI_ADRES])
            ->assertSessionHasErrors('adresler');

        $this->assertDatabaseCount('oauth_clients', 0);
    }

    public function test_olagan_ad_ve_adres_kabul_edilir(): void
    {
        $this->actingAs($this->admin())
            ->post(route('yonetim.istemciler.ekle'), [
                'name' => 'Ağ Paneli (EYS) - 2', 'adresler' => 'https://eys.ornek.test/giris/merkez/geri?a=1&b=2',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Ağ Paneli (EYS) - 2']);
    }
}
