<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * E-posta değişince "doğrulanmış" işareti DÜŞER.
 *
 * NEDEN: bu panel bir kimlik sağlayıcısıdır ve `email_verified` iddiasını
 * bağlı projelere o projelerin GÜVENDİĞİ bir bilgi olarak gönderir.
 * Projeler ilk eşleşmede hesabı bu iddiaya bakarak bulur: doğrulanmış
 * sayılan bir adres, yereldeki aynı adresli hesaba bağlanır.
 *
 * İşaret düşmeseydi şu olurdu: burada onaylanmış herhangi bir kullanıcı,
 * kendi profilinden e-postasını bir meslektaşının adresiyle değiştirir;
 * adresin ona ait olduğunu hiçbir zaman kanıtlamadığı hâlde iddia "true"
 * gitmeye devam eder; bağlı projede o adrese karşılık gelen ve henüz hiçbir
 * kimliğe bağlanmamış hesap ONUN hesabı olur. Kurulumdan hemen sonra
 * `yonetici-ekle` ile açılan hesap tam olarak böyle bir hesaptır — ilk SSO
 * girişine kadar bağsızdır.
 *
 * İşaret yalnızca DÜŞER; geri koymak bir insanın işidir (yönetici onayı ya
 * da doğrulama postası). Bkz. Admin\UserController.
 */
class EmailChangeVerificationDropsTest extends TestCase
{
    use RefreshDatabase;

    private function kullanici(array $ustune = []): User
    {
        return User::factory()->create([
            'name' => 'Deneme Kişi',
            'email' => 'kisi@ornek.test',
            'password' => Hash::make('parola-1234'),
            'email_verified_at' => now(),
            ...$ustune,
        ]);
    }

    #[Test]
    public function eposta_degisince_dogrulama_isareti_silinir(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)
            ->put(route('hesap.profil.kaydet'), ['name' => 'Deneme Kişi', 'email' => 'baskasi@ornek.test'])
            ->assertSessionHas('status');

        $this->assertNull(
            $kullanici->fresh()->email_verified_at,
            'E-posta değişti ama "doğrulanmış" işareti kaldı: kanıtlanmamış bir adres doğrulanmış sayılıyor.',
        );
    }

    /**
     * Asıl korunan şey bu: bağlı projelerin okuduğu iddia.
     */
    #[Test]
    public function degisiklikten_sonra_kimlik_ucu_dogrulanmadi_der(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)
            ->put(route('hesap.profil.kaydet'), ['name' => 'Deneme Kişi', 'email' => 'baskasi@ornek.test']);

        $this->actingAs($kullanici->fresh(), 'api')
            ->getJson(route('kimlik.kullanici'))
            ->assertOk()
            ->assertJsonPath('email', 'baskasi@ornek.test')
            ->assertJsonPath('email_verified', false);
    }

    /**
     * Adres değişmediyse işarete DOKUNULMAZ.
     *
     * Yalnızca adını düzelten biri, projelerdeki bağını kaybetmemeli.
     */
    #[Test]
    public function adres_ayniyken_sadece_ad_degisirse_isaret_kalir(): void
    {
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)
            ->put(route('hesap.profil.kaydet'), ['name' => 'Yeni Ad', 'email' => 'kisi@ornek.test'])
            ->assertSessionHas('status');

        $kullanici->refresh();

        $this->assertSame('Yeni Ad', $kullanici->name);
        $this->assertNotNull($kullanici->email_verified_at);
    }
}
