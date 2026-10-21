<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kimlik sunucusu.
 *
 * Sınanan şey OAuth'un kendisi değil — onu olgun bir kütüphane yapıyor —
 * bizim DAR TUTTUĞUMUZ yüzey: yalnızca kod akışı, birebir eşleşen dönüş
 * adresi, kısa jeton ömrü ve az bilgi veren bir kullanıcı ucu.
 */
class KimlikSunucusuTest extends TestCase
{
    use RefreshDatabase;

    private function istemci(array $adresler = ['https://panel-b.ornek.test/giris/merkez/geri']): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Network', $adresler);
    }

    /** Keşif belgesi istemciyi kuran kişiye uçları söyler. */
    #[Test]
    public function kesif_belgesi_uclari_bildirir(): void
    {
        $this->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonPath('response_types_supported', ['code'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);
    }

    /**
     * PAROLA AKIŞI KAPALI.
     *
     * Açık olsaydı istemci, kullanıcının parolasını kendi formunda toplayıp
     * merkeze gönderirdi — yani merkezin varlık sebebi ortadan kalkardı.
     */
    #[Test]
    public function parola_akisi_kapalidir(): void
    {
        $istemci = $this->istemci();
        $kullanici = User::factory()->create(['email' => 'kisi@ornek.test']);

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $istemci->getKey(),
            'client_secret' => $istemci->plainSecret,
            'username' => $kullanici->email,
            'password' => 'parola-1234',
        ])->assertStatus(400);
    }

    /**
     * DÖNÜŞ ADRESİ birebir eşleşmeli.
     *
     * Asıl koruma bu: kod, yalnızca kayıtlı adrese gönderilir. Eşleşme
     * gevşek olsaydı, saldırgan kendi adresine yönlendirip kodu alırdı.
     */
    #[Test]
    public function kayitli_olmayan_donus_adresi_reddedilir(): void
    {
        $istemci = $this->istemci();

        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $istemci->getKey(),
                'redirect_uri' => 'https://saldirgan.ornek.test/al',
                'response_type' => 'code',
                'code_challenge' => str_repeat('a', 43),
                'code_challenge_method' => 'S256',
            ]))
            /*
             * 401 "invalid_client": standart, eşleşmeyen dönüş adresini
             * istemcinin kendisini tanımamak sayar. Önemli olan kodun
             * saldırganın adresine GİTMEMESİ.
             */
            ->assertStatus(401);
    }

    /** Kendi projemiz: onay ekranı sorulmadan doğrudan dönülür. */
    #[Test]
    public function kayitli_donus_adresi_kabul_edilir(): void
    {
        $istemci = $this->istemci();

        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $istemci->getKey(),
                'redirect_uri' => 'https://panel-b.ornek.test/giris/merkez/geri',
                'response_type' => 'code',
                'code_challenge' => str_repeat('a', 43),
                'code_challenge_method' => 'S256',
            ]))
            ->assertRedirect();
    }

    /** Oturumsuz kullanıcı önce giriş penceresine gider, sonra izne. */
    #[Test]
    public function oturumsuz_kullanici_once_girise_gonderilir(): void
    {
        $istemci = $this->istemci();

        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $istemci->getKey(),
            'redirect_uri' => 'https://panel-b.ornek.test/giris/merkez/geri',
            'response_type' => 'code',
        ]))->assertRedirect(route('hizli-erisim', ['pencere' => 'giris']));
    }

    /** Kullanıcı ucu jetonsuz açılmaz. */
    /**
     * Kullanıcı ucu jetonsuz 401 döner — YÖNLENDİRMEZ.
     *
     * Yönlendirme dönseydi istemci, giriş sayfasının HTML'ini "başarılı"
     * sanıp jetonun geçersiz olduğunu anlamazdı.
     */
    #[Test]
    public function kullanici_ucu_jeton_ister(): void
    {
        $this->getJson('/oauth/kullanici')->assertUnauthorized();
        $this->get('/oauth/kullanici')->assertUnauthorized();
    }

    /**
     * Kullanıcı ucu AZ BİLGİ verir.
     *
     * Kimliği kanıtlamaya yetecek kadar: değişmez kimlik (sub uuid'dir,
     * e-posta değişse bile eşleşme bozulmaz), ad, e-posta ve rolün slug'ı.
     * İZİN LİSTESİ DÖNMEZ: merkez kapıyı açar, yetkiyi proje verir.
     */
    #[Test]
    public function kullanici_ucu_yalnizca_kimlik_dondurur(): void
    {
        $kullanici = User::factory()->yonetici()->create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'yonetici@ornek.test',
        ]);

        $yanit = $this->actingAs($kullanici, 'api')->getJson('/oauth/kullanici')->assertOk();

        $yanit->assertJson([
            'sub' => $kullanici->uuid,
            'name' => 'Sistem Yöneticisi',
            'email' => 'yonetici@ornek.test',
            'rol' => 'admin',
        ]);

        foreach (['durum', 'password', 'totp_secret', 'yetkiler', 'template'] as $olmamali) {
            $yanit->assertJsonMissingPath($olmamali);
        }
    }

    /**
     * ROL BİLGİDİR, EMİR DEĞİL.
     *
     * Projeler kendi hesabını açarken "bu kişi merkezde ne olarak tanınıyor"
     * sorusuna yanıt bulabilsin diye dönüyor; slug bağlı panellerde de aynı.
     * Rolsüz hesapta alan yine var ama boş — istemci "alan yok mu, rol yok
     * mu" diye ayırt etmek zorunda kalmasın.
     */
    #[Test]
    public function rolsuz_hesapta_rol_alani_bostur(): void
    {
        $kullanici = User::factory()->create(['rol_id' => null]);

        $this->actingAs($kullanici, 'api')
            ->getJson('/oauth/kullanici')
            ->assertOk()
            ->assertJson(['rol' => null, 'rol_adi' => null]);
    }

    /** Jeton ömrü kısa: kimlik lazım, uzun ömürlü erişim değil. */
    #[Test]
    public function jeton_omru_kisa_tutulur(): void
    {
        $this->assertSame(10, config('kimlik.jeton_dakika'));
        $this->assertSame(1, config('kimlik.yenileme_gun'));
    }

    /** İstemci kaydı https olmayan adresi kabul etmez. */
    #[Test]
    public function istemci_kaydi_guvensiz_adresi_reddeder(): void
    {
        $this->artisan('kimlik:istemci-ekle', ['--ad' => 'Kötü', '--adres' => ['http://ornek.test/geri']])
            ->assertFailed();

        $this->assertDatabaseMissing('oauth_clients', ['name' => 'Kötü']);
    }

    #[Test]
    public function istemci_kaydi_calisir_ve_kayda_gecer(): void
    {
        $this->artisan('kimlik:istemci-ekle', [
            '--ad' => 'Network',
            '--adres' => ['https://panel-b.ornek.test/giris/merkez/geri'],
        ])->assertSuccessful();

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Network']);
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'istemci-eklendi']);
    }

    /**
     * Askıdaki hesabın jetonu /oauth/kullanici'de 500 değil 403 alır.
     *
     * Web grubundaki hesap kapısı oturum kapatmaya çalışıyordu; jeton
     * guard'ında oturum olmadığı için istek patlıyordu.
     */
    public function test_askidaki_hesabin_jetonu_temiz_403_alir(): void
    {
        $kullanici = User::factory()->askida()->create();

        $this->actingAs($kullanici, 'api')
            ->getJson('/oauth/kullanici')
            ->assertForbidden()
            ->assertJsonPath('kod', 'hesap_etkin_degil');
    }
}
