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
 * Süre sayfaya SUNUCUDAN gelir; kilit (oturum-kilidi.js) başka bir sayı
 * tutmaz. Etiket yalnızca oturum açıkken basılır: giriş ekranında kilit
 * çalışmaz.
 */
class OturumKilidiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function oturum_acikken_sure_ve_adresler_basilir(): void
    {
        config(['session.lifetime' => 60]);

        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('yonetim.sistemler'))
            ->assertOk()
            ->assertSee('<meta name="oturum-suresi" content="60">', false)
            ->assertSee('<meta name="oturum-cikis" content="'.route('cikis').'">', false)
            ->assertSee('<meta name="oturum-giris" content="'.route('hizli-erisim').'">', false);
    }

    #[Test]
    public function oturum_yokken_kilit_yok(): void
    {
        $this->get(route('hizli-erisim'))->assertOk()->assertDontSee('oturum-suresi', false);
    }
}
