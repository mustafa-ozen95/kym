<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İŞLEMİN SONUCU EKRANDA SÖYLENİR.
 *
 * Denetleyicinin mesajı bıraktığı anahtar ile sayfanın baktığı anahtar
 * ayrışırsa mesaj sessizce düşer: hesap açılır ama kullanıcı açılıp
 * açılmadığını bilmez.
 *
 * Bildirim PANELLE AYNI bileşenden çıkar (`<x-ui.toast-host />` +
 * js/shared/toast.js): iki ayrı yol iki ayrı davranış demekti.
 */
final class RegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kayit_sonrasi_bildirim_sayfada_gorunur(): void
    {
        $yanit = $this->post(route('kayit'), [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@ornek.test',
            'password' => 'cok-gizli-1616',
            'password_confirmation' => 'cok-gizli-1616',
        ]);

        $yanit->assertSessionHas('status');

        /* Asıl sınanan: mesajın SAYFAYA çıkması. */
        $govde = $this->followingRedirects()
            ->post(route('kayit'), [
                'name' => 'İkinci Kişi',
                'email' => 'ikinci@ornek.test',
                'password' => 'cok-gizli-1616',
                'password_confirmation' => 'cok-gizli-1616',
            ])
            ->assertOk()
            ->assertSee('toast-host', escape: false)
            ->getContent();

        $kutu = str($govde)->after('class="toast-host"')->before('>')->toString();

        $this->assertStringContainsString('Hesabınız açıldı', $kutu);
        $this->assertStringContainsString('data-flash-variant="success"', $kutu);
    }

    /** Hata bildirimi de aynı kutudan, hata rengiyle çıkar. */
    public function test_hata_bildirimi_de_gorunur(): void
    {
        $govde = $this->withSession(['error' => 'Doğrulama bağlantısı geçersiz.'])
            ->get(route('hizli-erisim'))
            ->assertOk()
            ->getContent();

        $kutu = str($govde)->after('class="toast-host"')->before('>')->toString();

        $this->assertStringContainsString('Doğrulama bağlantısı geçersiz.', $kutu);
        $this->assertStringContainsString('data-flash-variant="danger"', $kutu);
    }

    /** Bildirim yokken kutu boş gelir; betik boş metinde hiçbir şey göstermez. */
    public function test_bildirim_yoksa_kutu_bos(): void
    {
        $govde = $this->get(route('hizli-erisim'))->assertOk()->getContent();

        $kutu = str($govde)->after('class="toast-host"')->before('>')->toString();

        $this->assertStringContainsString('data-flash=""', $kutu);
    }

    /**
     * PANEL DE AYNI KUTUYU KULLANIR.
     *
     * Sözleşmenin iki ucu var; biri değişip öbürü kalırsa bildirim yine
     * sessizce düşer.
     */
    public function test_panel_ekraninda_da_ayni_kutu_var(): void
    {
        $this->actingAs(User::factory()->yonetici()->ikiAdimli()->create())
            ->withSession(['status' => 'Ayarlar kaydedildi.'])
            ->get(route('yonetim.sistemler'))
            ->assertOk()
            ->assertSee('data-flash="Ayarlar kaydedildi."', escape: false);
    }
}
