<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * İngilizce ad değişikliğinden önceki adresler (yer imleri, paylaşılmış
 * bağlantılar) yeni adrese kalıcı yönlendirilir; parametre taşınır, yetki
 * hedefte sorulur.
 */
final class LegacyUrlRedirectTest extends TestCase
{
    #[Test]
    public function eski_salon_adresi_yeni_salon_adresine_gider(): void
    {
        $this->get('/salon/dc-yonetim')->assertStatus(301)->assertRedirect('/group/dc-yonetim');
    }

    #[Test]
    public function eski_yonetim_ve_hesap_adresleri_yonlendirilir(): void
    {
        foreach ([
            '/hesap/profil' => '/account/profile',
            '/yonetim/sistemler' => '/admin/systems',
            '/yonetim/ayarlar/veritabani' => '/admin/settings/database',
            '/yonetim/ayarlar/roller/7' => '/admin/settings/roles/7',
            '/yonetim/kullanicilar/12/yetkiler' => '/admin/users/12/permissions',
        ] as $eski => $yeni) {
            $this->get($eski)->assertStatus(301)->assertRedirect($yeni);
        }
    }

    /** Yönlendirme yetkiyi atlatmaz: oturumsuz istek hedefte girişe döner. */
    #[Test]
    public function yonlendirme_yetkiyi_atlatmaz(): void
    {
        $hedef = $this->get('/yonetim/ayarlar')->headers->get('Location');

        $this->get($hedef)->assertRedirect();
        $this->assertStringNotContainsString('/admin', (string) $this->get($hedef)->headers->get('Location'));
    }
}
