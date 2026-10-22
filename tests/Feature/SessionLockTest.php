<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ekran kilidi: hareketsiz sekme oturumu kapatır.
 *
 * Süre sayfaya SUNUCUDAN gelir; kilit (session-lock.js) başka bir sayı
 * tutmaz. Etiket yalnızca oturum açıkken basılır: giriş ekranında kilit
 * çalışmaz.
 */
class SessionLockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function oturum_acikken_sure_ve_adresler_basilir(): void
    {
        config(['session.lifetime' => 60]);

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('admin.systems'))
            ->assertOk()
            ->assertSee('<meta name="session-timeout" content="60">', false)
            ->assertSee('<meta name="session-logout" content="'.route('logout').'">', false)
            ->assertSee('<meta name="session-login" content="'.route('quick-access').'">', false);
    }

    #[Test]
    public function oturum_yokken_kilit_yok(): void
    {
        $this->get(route('quick-access'))->assertOk()->assertDontSee('session-timeout', false);
    }
}
