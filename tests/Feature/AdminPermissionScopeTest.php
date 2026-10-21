<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as YonlendirmeRotasi;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * Yönetim grubundaki HER rota kendi yeteneğini ister.
 *
 * Grubun `can:yonet` kapısı yalnızca kapıdır: herhangi bir yönetim yetkisi
 * olan biri geçer. Yetenek controller'da sorulur; bir eylem unutulursa
 * (kalıcı silme, sıralama gibi) kapıdan geçen herkes onu yapabilir.
 */
final class AdminPermissionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_her_yonetim_rotasi_kendi_yetenegini_ister(): void
    {
        $eksik = [];

        foreach (Route::getRoutes() as $rota) {
            /** @var YonlendirmeRotasi $rota */
            if (! str_starts_with($rota->uri(), 'yonetim')) {
                continue;
            }

            $yetenekler = array_filter(
                $rota->gatherMiddleware(),
                fn ($ara): bool => is_string($ara) && str_starts_with($ara, 'can:') && $ara !== 'can:yonet',
            );

            if ($yetenekler === []) {
                $eksik[] = implode('|', $rota->methods()).' '.$rota->uri();
            }
        }

        $this->assertSame([], $eksik, 'Yetenek istemeyen yönetim rotaları var.');
    }

    public function test_kalici_silme_istemci_sil_yetkisi_ister(): void
    {
        $this->yetkiDagitimiAc('muhasebe', ['istemci.gor']);

        /** @var OauthClient $istemci */
        $istemci = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Eski', ['https://a.test/geri']);
        $istemci->forceFill(['revoked' => true])->save();

        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->delete(route('yonetim.istemciler.kalici-sil', $istemci))
            ->assertForbidden();

        $this->assertDatabaseHas('oauth_clients', ['id' => $istemci->getKey()]);
    }

    public function test_kutucuk_siralama_sistem_duzenle_ister(): void
    {
        $this->yetkiDagitimiAc('muhasebe', ['sistem.gor']);

        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->put(route('yonetim.sistemler.sirala', Group::create(['anahtar' => 'deneme', 'ad' => 'Deneme'])), ['sira' => []])
            ->assertForbidden();
    }
}
