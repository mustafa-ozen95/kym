<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\OauthClient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kimliklerin şu anki durumu — bağlı projeler için arka kanal.
 *
 * NEDEN VAR: merkezde rol değişince ya da hesap askıya alınınca, bağlı
 * projedeki kopya eskiyor ve o kişi orada erişmeye devam ediyordu. Buradaki
 * testler ucun çalıştığını değil, KİMİN SORABİLECEĞİNİ kilitler: sırrı
 * olmayan soramaz, bağlantısı kaldırılmış uygulama soramaz ve hiç kimse
 * "hepsini listele" diyemez.
 */
final class IdentityStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: string, 1: string} */
    private function istemci(): array
    {
        $istemci = app(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Network', ['https://network.test/geri']);

        return [(string) $istemci->getKey(), (string) $istemci->plainSecret];
    }

    #[Test]
    public function sirri_olmayan_soramaz(): void
    {
        [$kimlik] = $this->istemci();
        $kullanici = User::factory()->create();

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => 'yanlis-sir',
            'identities' => [$kullanici->uuid],
        ])->assertUnauthorized();
    }

    /** Bağlantısı kaldırılmış uygulama arka kanaldan da konuşamaz. */
    #[Test]
    public function kaldirilmis_istemci_soramaz(): void
    {
        [$kimlik, $sir] = $this->istemci();
        $kullanici = User::factory()->create();

        OauthClient::whereKey($kimlik)->update(['revoked' => true]);

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => $sir,
            'identities' => [$kullanici->uuid],
        ])->assertUnauthorized();
    }

    #[Test]
    public function durum_ve_rol_doner(): void
    {
        [$kimlik, $sir] = $this->istemci();

        $etkin = User::factory()->rol('network')->create(['durum' => Status::Etkin]);
        $askida = User::factory()->rol('teknik')->create(['durum' => Status::Askida]);

        $yanit = $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => $sir,
            'identities' => [$etkin->uuid, $askida->uuid],
        ])->assertOk();

        $yanit->assertJsonPath("identities.{$etkin->uuid}.role", 'network');
        $yanit->assertJsonPath("identities.{$etkin->uuid}.can_enter", true);
        $yanit->assertJsonPath("identities.{$askida->uuid}.can_enter", false);
    }

    /**
     * SİLİNEN KİMLİK "YOK" DÖNER, sessizce atlanmaz.
     *
     * Atlansaydı proje "sormadım mı, yanıt mı gelmedi" ayrımını yapamaz ve
     * silinmiş bir hesabı açık bırakırdı.
     */
    #[Test]
    public function silinen_kimlik_yok_doner(): void
    {
        [$kimlik, $sir] = $this->istemci();
        $silinen = (string) Str::uuid7();

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => $sir,
            'identities' => [$silinen],
        ])
            ->assertOk()
            ->assertJsonPath("identities.{$silinen}.status", 'missing');
    }

    /**
     * YALNIZCA SORULAN SÖYLENİR.
     *
     * "Hepsini listele" diyen bir uç olsaydı, bağlı tek bir uygulamanın
     * sırrını ele geçiren biri merkezin bütün kadrosunu okurdu.
     */
    #[Test]
    public function kimlik_listesi_zorunludur(): void
    {
        [$kimlik, $sir] = $this->istemci();

        User::factory()->count(3)->create();

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => $sir,
        ])->assertUnprocessable();
    }

    /** Sıralı birincil anahtar geçmez: identities uuid ile sorulur. */
    #[Test]
    public function sirali_anahtarla_sorulamaz(): void
    {
        [$kimlik, $sir] = $this->istemci();
        $kullanici = User::factory()->create();

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik,
            'client_secret' => $sir,
            'identities' => [(string) $kullanici->getKey()],
        ])->assertUnprocessable();
    }

    /**
     * KISITLI İSTEMCİ kapsamı dışındaki kişinin e-postasını ve rolünü ALAMAZ.
     *
     * Yalnızca "girebilir değil" der; kadronun geri kalanını okutmaz. Liste
     * boş istemci (öteki testler) herkesi görmeye devam eder.
     */
    #[Test]
    public function kisitli_istemci_kapsam_disindakinin_eposta_ve_rolunu_goremez(): void
    {
        [$kimlik, $sir] = $this->istemci();
        OauthClient::findOrFail($kimlik)->roller()->attach(Role::where('slug', 'network')->value('id'));

        $icerde = User::factory()->rol('network')->create();
        $disarda = User::factory()->rol('teknik')->create();
        $rolsuz = User::factory()->create(['rol_id' => null]);

        $yanit = $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik, 'client_secret' => $sir,
            'identities' => [$icerde->uuid, $disarda->uuid, $rolsuz->uuid],
        ])->assertOk();

        $yanit->assertJsonPath("identities.{$icerde->uuid}.email", $icerde->email);
        $yanit->assertJsonPath("identities.{$icerde->uuid}.role", 'network');
        $yanit->assertJsonPath("identities.{$icerde->uuid}.can_enter", true);

        foreach ([$disarda, $rolsuz] as $kisi) {
            $yanit->assertJsonPath("identities.{$kisi->uuid}.can_enter", false);
            $yanit->assertJsonPath("identities.{$kisi->uuid}.role", null);
            $yanit->assertJsonMissingPath("identities.{$kisi->uuid}.email");
        }
    }

    /** Kapsamdaki ama ASKIYA ALINMIŞ kişi tanınmaya devam eder: proje hesabını kapatabilmeli. */
    #[Test]
    public function kisitli_istemci_kapsamdaki_askidaki_kisiyi_tanir(): void
    {
        [$kimlik, $sir] = $this->istemci();
        OauthClient::findOrFail($kimlik)->roller()->attach(Role::where('slug', 'network')->value('id'));

        $askida = User::factory()->rol('network')->create(['durum' => Status::Askida]);

        $this->postJson(route('api.identity-status'), [
            'client_id' => $kimlik, 'client_secret' => $sir, 'identities' => [$askida->uuid],
        ])->assertOk()
            ->assertJsonPath("identities.{$askida->uuid}.can_enter", false)
            ->assertJsonPath("identities.{$askida->uuid}.email", $askida->email);
    }
}
