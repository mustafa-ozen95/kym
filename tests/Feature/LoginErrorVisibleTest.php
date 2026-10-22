<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Giriş hatası KULLANICIYA GÖRÜNÜR.
 *
 * Pencere yalnızca adresteki ?window= ile açılsaydı yetmezdi: kullanıcı
 * pencereyi bağlantıdan açtığında adreste o yazmaz (betik açar); giriş
 * başarısız olunca Laravel "geri" döndürür ve pencere kapalı gelirdi. Ör.
 * "Hesabınız beklemede" iletisi ekranda hiç görünmez, kullanıcı neden
 * giremediğini öğrenemezdi. Pencere doğrulama hatasından da açılır.
 */
final class LoginErrorVisibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_onaylanmamis_hesap_sebebini_ogrenir(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Status::Beklemede,
            'password' => bcrypt('parola-12345'),
            'email_verified_at' => now(),
        ]);

        $yanit = $this->from(route('quick-access'))->post(route('login'), [
            'email' => $kullanici->email,
            'password' => 'parola-12345',
        ]);

        $yanit->assertSessionHasErrors('email');
        $this->assertGuest();

        /* Dönülen sayfada pencere AÇIK ve sebep yazılı olmalı. */
        $sayfa = $this->followingRedirects()->from(route('quick-access'))->post(route('login'), [
            'email' => $kullanici->email,
            'password' => 'parola-12345',
        ]);

        /*
         * PENCERENİN AÇIK OLDUĞU sınanıyor, metnin varlığı DEĞİL: kapalı
         * pencere de gövdesiyle birlikte basılıyor, yalnızca "hidden"
         * alıyor. Metne bakan bir sınama hatayı hiç yakalamazdı.
         */
        $sayfa->assertOk()
            ->assertSee('Yönetici hesabınızı etkinleştirdiğinde girebilirsiniz.')
            ->assertDontSee('aria-labelledby="window-login-title" hidden', false);
    }

    public function test_yanlis_parolada_da_pencere_acik_gelir(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Status::Etkin,
            'password' => bcrypt('dogru-parola'),
            'email_verified_at' => now(),
        ]);

        $this->followingRedirects()->from(route('quick-access'))
            ->post(route('login'), ['email' => $kullanici->email, 'password' => 'yanlis'])
            ->assertOk()
            ->assertSee('E-posta veya parola hatalı.')
            ->assertDontSee('aria-labelledby="window-login-title" hidden', false);
    }
}
