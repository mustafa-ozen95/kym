<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\OauthClient;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Identity\SetupFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * Bağlı uygulamalar.
 *
 * Yeni bir siteyi merkeze bağlamak, o siteye "bu kullanıcı kim" sorusunu
 * sorma hakkı vermektir. Testlerin kilitlediği şey bu hakkın KOLAY
 * dağıtılmaması: adres birebir ve https olmalı, sır bir kez görünmeli,
 * kaldırılan uygulama gerçekten durmalı.
 */
final class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function rolde(string $slug): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    private function istemci(string $ad = 'Deneme'): OauthClient
    {
        /** @var OauthClient $istemci */
        $istemci = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            $ad,
            ['https://deneme.ornek.test/login/center/callback'],
        );

        return $istemci;
    }

    public function test_yonetici_yeni_uygulama_baglar(): void
    {
        $this->actingAs($this->rolde('admin'))
            ->post(route('admin.clients.store'), [
                'name' => 'Yeni Panel',
                'addresses' => 'https://yeni.ornek.test/login/center/callback',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Yeni Panel']);
    }

    /**
     * SIR BİR KEZ GÖRÜNÜR ve .env bloğuyla birlikte gelir.
     *
     * Sonradan okunamaz; saklanmıyor. Bloğun hazır üretilmesi, elle yazılan
     * bir örneğin ilk adres değişikliğinde yanlış talimat vermesini önlüyor.
     */
    public function test_sir_bir_kez_env_blogu_ile_gosterilir(): void
    {
        $yanit = $this->actingAs($this->rolde('admin'))
            ->post(route('admin.clients.store'), [
                'name' => 'Yeni Panel',
                'addresses' => 'https://yeni.ornek.test/login/center/callback',
            ]);

        $yanit->assertSessionHas('yeniSir');

        $env = session('yeniEnv');

        $this->assertStringContainsString('IDENTITY_CLIENT_ID='.session('yeniIstemci'), $env);
        $this->assertStringContainsString('IDENTITY_CLIENT_SECRET='.session('yeniSir'), $env);
        $this->assertStringContainsString('IDENTITY_REDIRECT_URI=https://yeni.ornek.test/login/center/callback', $env);
    }

    /**
     * Listede sır YOK.
     *
     * Bir kez göründükten sonra ekranda kalsaydı, ekranı açık unutan biri onu
     * paylaşmış olurdu.
     */
    public function test_listede_sir_basilmaz(): void
    {
        $istemci = $this->istemci();

        $this->actingAs($this->rolde('admin'))
            ->get(route('admin.clients'))
            ->assertOk()
            ->assertSee($istemci->name)
            ->assertDontSee($istemci->secret);
    }

    /**
     * DÖNÜŞ ADRESİ https OLMAK ZORUNDA.
     *
     * Kod adres çubuğunda taşınıyor; şifresiz bir adrese göndermek aradaki
     * herkese kodu vermek demektir.
     */
    public function test_sifresiz_adres_kabul_edilmez(): void
    {
        $this->actingAs($this->rolde('admin'))
            ->post(route('admin.clients.store'), [
                'name' => 'Yeni Panel',
                'addresses' => 'http://yeni.ornek.test/login/center/callback',
            ])
            ->assertSessionHasErrors('addresses');

        $this->assertDatabaseMissing('oauth_clients', ['name' => 'Yeni Panel']);
    }

    public function test_bicimsiz_adres_kabul_edilmez(): void
    {
        $this->actingAs($this->rolde('admin'))
            ->post(route('admin.clients.store'), ['name' => 'Yeni Panel', 'addresses' => 'yeni.ornek.test'])
            ->assertSessionHasErrors('addresses');
    }

    /** Birden çok adres satır satır girilir; boş satırlar atılır. */
    public function test_birden_cok_adres_satir_satir_girilir(): void
    {
        $this->actingAs($this->rolde('admin'))
            ->post(route('admin.clients.store'), [
                'name' => 'Çok Adresli',
                'addresses' => "https://a.ornek.test/login/center/callback\n\nhttps://b.ornek.test/login/center/callback\n",
            ])
            ->assertRedirect();

        $istemci = OauthClient::firstWhere('name', 'Çok Adresli');

        $this->assertSame([
            'https://a.ornek.test/login/center/callback',
            'https://b.ornek.test/login/center/callback',
        ], (array) $istemci->redirect_uris);
    }

    /**
     * SIR YENİLENİNCE ESKİ JETONLAR DA DÜŞER.
     *
     * Yalnızca sırrı değiştirmek, sızıntıyla alınmış bir jetonu ömrü dolana
     * kadar çalışır bırakırdı — oysa sır tam da bu yüzden yenileniyor.
     */
    public function test_sir_yenilenince_jetonlar_dusurulur(): void
    {
        $istemci = $this->istemci();
        $eski = $istemci->secret;

        $jeton = $istemci->tokens()->create([
            'id' => 'jeton-1',
            'user_id' => $this->rolde('muhasebe')->id,
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->rolde('admin'))
            ->put(route('admin.clients.secret', $istemci))
            ->assertRedirect()
            ->assertSessionHas('yeniSir');

        $this->assertNotSame($eski, $istemci->fresh()->secret);
        $this->assertTrue($jeton->fresh()->revoked);
    }

    /**
     * SİLİNMEZ, İPTAL EDİLİR.
     *
     * Kayıt silinseydi denetimdeki "şu uygulama şu kullanıcıyı sordu"
     * satırları hangi uygulamaya ait olduğunu kaybederdi.
     */
    public function test_baglanti_kaldirilir_kayit_silinmez(): void
    {
        $istemci = $this->istemci();

        $this->actingAs($this->rolde('admin'))
            ->delete(route('admin.clients.revoke', $istemci))
            ->assertRedirect();

        $this->assertModelExists($istemci);
        $this->assertTrue($istemci->fresh()->revoked);
    }

    public function test_adres_duzenlenebilir(): void
    {
        $istemci = $this->istemci();

        $this->actingAs($this->rolde('admin'))
            ->put(route('admin.clients.update', $istemci), [
                'name' => 'Yeni Ad',
                'addresses' => 'https://baska.ornek.test/login/center/callback',
            ])
            ->assertRedirect();

        $this->assertSame('Yeni Ad', $istemci->fresh()->name);
        $this->assertSame(['https://baska.ornek.test/login/center/callback'], (array) $istemci->fresh()->redirect_uris);
    }

    /* ── Yetki ── */

    /**
     * Uygulama bağlamak AYRI bir yetki.
     *
     * "Kutucukları düzenle" yetkisi bunu kapsasaydı, içerik editörü merkeze
     * kendi sitesini bağlayıp kimlik toplayabilirdi.
     */
    public function test_editor_uygulama_baglayamaz(): void
    {
        $editor = $this->rolde('network');

        $this->actingAs($editor)->get(route('admin.clients'))->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.clients.store'), [
                'name' => 'Sızma', 'addresses' => 'https://sizma.example.test/geri',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('oauth_clients', ['name' => 'Sızma']);
    }

    /** Sır yenilemek, düzenlemekten AYRI: uygulamanın girişini o an durdurur. */
    public function test_sir_yenilemek_ayri_yetki_ister(): void
    {
        /* Düzenek sınanıyor: varsayılanda dağıtım kapalı (bkz. TestCase). */
        $this->yetkiDagitimiAc();

        $istemci = $this->istemci();
        $sorumlu = $this->rolde('muhasebe');

        UserPermission::create([
            'user_id' => $sorumlu->id, 'yetenek' => 'istemci.duzenle', 'etki' => 'izin',
        ]);
        UserPermission::create([
            'user_id' => $sorumlu->id, 'yetenek' => 'istemci.gor', 'etki' => 'izin',
        ]);

        $this->actingAs($sorumlu)
            ->put(route('admin.clients.update', $istemci), [
                'name' => 'Yeni Ad', 'addresses' => 'https://deneme.ornek.test/login/center/callback',
            ])
            ->assertRedirect();

        $this->actingAs($sorumlu)
            ->put(route('admin.clients.secret', $istemci))
            ->assertForbidden();
    }

    /** Menüde yalnızca yetkisi olan görür. */
    public function test_uygulamalar_menude_yetkiyle_gorunur(): void
    {
        /* Düzenek sınanıyor: varsayılanda dağıtım kapalı (bkz. TestCase). */
        $this->yetkiDagitimiAc();

        $this->actingAs($this->rolde('admin'))
            ->get(route('admin.clients'))
            ->assertOk()
            ->assertSee('Uygulamalar');

        $this->actingAs($this->rolde('network'))
            ->get(route('admin.systems'))
            ->assertOk()
            ->assertDontSee('>Uygulamalar<', false);
    }

    /** Adımlar ve keşif adresi ekranda: bağlayan kişi başka bir belge aramasın. */
    public function test_entegrasyon_adimlari_ekranda_durur(): void
    {
        $this->istemci();

        $this->actingAs($this->rolde('admin'))
            ->get(route('admin.clients'))
            ->assertOk()
            ->assertSee('Laravel projesinde yapılacaklar')
            ->assertSee('IdentityCenter.php')
            ->assertSee(route('identity.discovery'));
    }

    /**
     * PHP DIŞINDAKİ diller için protokol da ekranda.
     *
     * Yazılmasaydı "bizim dosyamız yoksa bağlanamazsın" izlenimi verirdi;
     * oysa merkez standart OAuth2 + OIDC keşfi konuşuyor.
     */
    public function test_baska_diller_icin_protokol_ekranda_durur(): void
    {
        $istemci = $this->istemci();

        $this->actingAs($this->rolde('admin'))
            ->get(route('admin.clients'))
            ->assertOk()
            ->assertSee('Başka bir dille bağlanmak')
            ->assertSee('code_challenge_method')
            ->assertSee('S256')
            ->assertSee('openid-client')
            ->assertSee($istemci->getKey());
    }

    /**
     * TEK DOSYA indirilir ve SIR İÇİNDE DEĞİLDİR.
     *
     * Dosya kaynak denetimine girebilir ve tarayıcının indirilenler
     * klasöründe kalır; sır .env'den okunur.
     */
    public function test_kurulum_dosyasi_indirilir_ve_sir_icinde_degildir(): void
    {
        $istemci = $this->istemci();

        $yanit = $this->actingAs($this->rolde('admin'))
            ->get(route('admin.clients.setup', $istemci));

        $yanit->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="IdentityCenter.php"');

        $icerik = $yanit->getContent();

        $this->assertStringContainsString('class IdentityCenter extends ServiceProvider', $icerik);
        $this->assertStringContainsString($istemci->getKey(), $icerik);
        $this->assertStringContainsString('https://deneme.ornek.test/login/center/callback', $icerik);
        $this->assertStringNotContainsString($istemci->secret, $icerik);
    }

    /** Üretilen dosya GEÇERLİ PHP olmalı; bozuksa proje hiç açılmaz. */
    public function test_kurulum_dosyasi_gecerli_php_uretir(): void
    {
        $icerik = app(SetupFile::class)->icerik($this->istemci());

        $yol = tempnam(sys_get_temp_dir(), 'kimlik-').'.php';
        file_put_contents($yol, $icerik);

        exec('php -l '.escapeshellarg($yol).' 2>&1', $cikti, $kod);
        @unlink($yol);

        $this->assertSame(0, $kod, implode("\n", $cikti));
    }

    public function test_kurulum_dosyasi_yetki_ister(): void
    {
        $this->actingAs($this->rolde('network'))
            ->get(route('admin.clients.setup', $this->istemci()))
            ->assertForbidden();
    }
}
