<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Jobs\NotifySharedLogoutJob;
use App\Models\OauthClient;
use App\Models\Role;
use App\Models\User;
use App\Services\SharedLogout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ORTAK ÇIKIŞ: bir panelden (ör. EYS) çıkış yapan kişi merkezden de çıkmış
 * olmalı.
 *
 * Merkez ve paneller kendi oturumlarını tutar; kişinin son çıkış anından
 * ÖNCE açılmış her oturum kapanır. Çıkış merkezden ya da panelden gelir,
 * paneller imzalı bildirimle haberdar edilir.
 */
final class SharedLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function istemci(string $kok = 'https://eys.ornek.test', string $ad = 'EYS'): OauthClient
    {
        $istemci = new OauthClient;

        $istemci->forceFill([
            'name' => $ad,
            'secret' => bcrypt('sir'),
            'bildirim_sirri' => 'bildirim-sirri',
            'provider' => 'users',
            'redirect_uris' => [$kok.'/login/center/callback'],
            'grant_types' => ['authorization_code'],
            'revoked' => false,
        ])->save();

        return $istemci;
    }

    #[Test]
    public function giris_oturumun_acilis_anini_yazar(): void
    {
        $kullanici = User::factory()->create(['durum' => Status::Etkin, 'password' => bcrypt('Parola-123456')]);
        $once = SharedLogout::simdiMs();

        $this->post(route('login'), ['email' => $kullanici->email, 'password' => 'Parola-123456']);

        $this->assertAuthenticatedAs($kullanici);
        $this->assertGreaterThanOrEqual($once, (int) session(SharedLogout::SESSION_KEY));
    }

    #[Test]
    public function cikistan_once_acilmis_oturum_kapanir_sonraki_kalir(): void
    {
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);
        User::query()->whereKey($kullanici->getKey())->update(['son_cikis_ms' => 2_000]);

        $this->actingAs($kullanici->fresh())
            ->withSession([SharedLogout::SESSION_KEY => 1_000])
            ->get(route('account.profile'))
            ->assertRedirect(route('quick-access', ['window' => 'login']))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($kullanici->fresh())
            ->withSession([SharedLogout::SESSION_KEY => 3_000])
            ->get(route('account.profile'))
            ->assertOk();
    }

    #[Test]
    public function merkezden_cikis_ani_yazar_ve_panellere_bildirir(): void
    {
        Queue::fake();
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);

        $this->actingAs($kullanici)->post(route('logout'))->assertRedirect();

        $an = (int) $kullanici->fresh()->son_cikis_ms;
        $this->assertGreaterThan(0, $an);
        Queue::assertPushed(NotifySharedLogoutJob::class,
            fn (NotifySharedLogoutJob $is): bool => $is->subject === (string) $kullanici->uuid && $is->an === $an);
    }

    #[Test]
    public function panel_kendi_kullanicisinin_cikisini_bildirir(): void
    {
        Queue::fake();
        $this->istemci();
        $istemci = OauthClient::where('name', 'EYS')->sole();
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);

        $this->postJson(route('api.session-closed'), [
            'client_id' => $istemci->getKey(), 'client_secret' => 'yanlis', 'subject' => $kullanici->uuid,
        ])->assertStatus(401);
        $this->assertNull($kullanici->fresh()->son_cikis_ms);

        $this->postJson(route('api.session-closed'), [
            'client_id' => $istemci->getKey(), 'client_secret' => 'sir', 'subject' => $kullanici->uuid,
        ])->assertOk();

        $this->assertNotNull($kullanici->fresh()->son_cikis_ms);
        Queue::assertPushed(NotifySharedLogoutJob::class);
    }

    #[Test]
    public function panel_kapsami_disindaki_kisiyi_cikaramaz(): void
    {
        Queue::fake();
        $istemci = $this->istemci();
        $istemci->roller()->attach(Role::where('slug', 'network')->value('id'));
        $kullanici = User::factory()->create(['durum' => Status::Etkin, 'rol_id' => Role::where('slug', 'teknik')->value('id')]);

        $this->postJson(route('api.session-closed'), [
            'client_id' => $istemci->getKey(), 'client_secret' => 'sir', 'subject' => $kullanici->uuid,
        ])->assertOk();

        $this->assertNull($kullanici->fresh()->son_cikis_ms);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function kimlik_durumu_son_cikisi_verir(): void
    {
        $istemci = $this->istemci();
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);
        User::query()->whereKey($kullanici->getKey())->update(['son_cikis_ms' => 1_234]);

        $this->postJson(route('api.identity-status'), [
            'client_id' => $istemci->getKey(), 'client_secret' => 'sir', 'identities' => [$kullanici->uuid],
        ])->assertOk()->assertJsonPath('identities.'.$kullanici->uuid.'.last_logout', 1_234);
    }

    #[Test]
    public function bildirim_ani_imzayla_tasir_ve_kapsam_disina_gitmez(): void
    {
        Http::fake();
        $this->istemci('https://eys.ornek.test');
        $kisitli = $this->istemci('https://kisitli.ornek.test', 'Kısıtlı');
        $kisitli->roller()->attach(Role::where('slug', 'network')->value('id'));
        $kullanici = User::factory()->create(['durum' => Status::Etkin, 'rol_id' => Role::where('slug', 'teknik')->value('id')]);
        $sub = (string) $kullanici->uuid;

        (new NotifySharedLogoutJob($sub, 5_000))->handle();

        Http::assertSent(fn ($istek): bool => $istek->url() === 'https://eys.ornek.test/api/identity/logout'
            && $istek['subject'] === $sub && $istek['time'] === 5_000
            && $istek->header('X-Identity-Signature')[0] === hash_hmac('sha256', 'logout|'.$sub.'|5000', 'bildirim-sirri'));
        Http::assertNotSent(fn ($istek): bool => str_starts_with($istek->url(), 'https://kisitli.ornek.test'));
    }

    /** Ekran kilidinin çıkışı (süre doldu) ortak değildir: paneller etkilenmez. */
    #[Test]
    public function zaman_asimi_cikisi_ortak_degildir(): void
    {
        Queue::fake();
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);

        $this->actingAs($kullanici)->withHeaders(['X-Oturum-Zaman-Asimi' => '1'])->post(route('logout'))->assertRedirect();

        $this->assertGuest();
        $this->assertNull($kullanici->fresh()->son_cikis_ms);
        Queue::assertNotPushed(NotifySharedLogoutJob::class);
    }

    #[Test]
    public function oturum_suresi_iki_saattir(): void
    {
        $this->assertSame(120, (int) config('session.lifetime'));
    }
}
