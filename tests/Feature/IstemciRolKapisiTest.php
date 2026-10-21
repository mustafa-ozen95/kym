<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\OauthIstemci;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HANGİ ROL HANGİ UYGULAMAYA GİREBİLİR — kararı MERKEZ verir.
 *
 * Eskiden her panel kendi listesini tutuyordu: aynı karar birkaç yerde yazılı,
 * biri değişince öbürleri sessizce eski kalıyordu. Artık kapı burada ve
 * panellerin yapması gereken tek şey merkezin yanıtını uygulamak.
 */
final class IstemciRolKapisiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OauthIstemci, 1: string} */
    private function istemci(): array
    {
        $istemci = app(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Satış', ['https://satis.test/geri']);

        return [OauthIstemci::findOrFail($istemci->getKey()), (string) $istemci->plainSecret];
    }

    private function kullanici(string $rolSlug): User
    {
        return User::factory()->create(['rol_id' => Rol::where('slug', $rolSlug)->value('id')]);
    }

    #[Test]
    public function liste_bossa_kapi_herkese_aciktir(): void
    {
        /*
         * Kısıt eklemek BİLİNÇLİ bir adımdır. Boş liste "kimse giremesin"
         * anlamına gelseydi yeni açılan her istemci, kimsenin giremediği ve
         * sebebi görünmeyen bir uygulama olurdu.
         */
        [$istemci] = $this->istemci();

        $this->assertTrue($istemci->erisebilirMi($this->kullanici('teknik')));
        $this->assertTrue($istemci->erisebilirMi($this->kullanici('muhasebe')));
    }

    #[Test]
    public function liste_doluysa_yalnizca_o_roller_girer(): void
    {
        [$istemci] = $this->istemci();
        $istemci->roller()->sync([Rol::where('slug', 'muhasebe')->value('id')]);

        $this->assertTrue($istemci->erisebilirMi($this->kullanici('muhasebe')));
        $this->assertFalse($istemci->erisebilirMi($this->kullanici('teknik')));
    }

    #[Test]
    public function rolsuz_kullanici_liste_doluyken_giremez(): void
    {
        /* Rolsüz hesap, merkezde yeni açılmış ve henüz kimsenin bakmadığı hesaptır. */
        [$istemci] = $this->istemci();
        $istemci->roller()->sync([Rol::where('slug', 'muhasebe')->value('id')]);

        $this->assertFalse($istemci->erisebilirMi(User::factory()->create(['rol_id' => null])));
    }

    #[Test]
    public function durum_ucu_girebiliri_uygulamaya_gore_soyler(): void
    {
        [$istemci, $sir] = $this->istemci();
        $istemci->roller()->sync([Rol::where('slug', 'muhasebe')->value('id')]);

        $muhasebeci = $this->kullanici('muhasebe');
        $teknik = $this->kullanici('teknik');

        $yanit = $this->postJson(route('api.kimlik-durumu'), [
            'client_id' => $istemci->getKey(),
            'client_secret' => $sir,
            'kimlikler' => [$muhasebeci->uuid, $teknik->uuid],
        ])->assertOk();

        /*
         * İkisi de merkezde ETKİN; fark yalnızca bu uygulamaya erişimlerinde.
         * Tek bir "etkin mi" yanıtı dönseydi, girmemesi gereken rol o panelde
         * de açık kalırdı.
         */
        $yanit->assertJsonPath("kimlikler.{$muhasebeci->uuid}.girebilir", true);
        $yanit->assertJsonPath("kimlikler.{$teknik->uuid}.girebilir", false);
    }

    #[Test]
    public function hesap_acma_izni_uygulama_basinadir(): void
    {
        [$istemci] = $this->istemci();
        $istemci->roller()->sync([Rol::where('slug', 'muhasebe')->value('id')]);

        $muhasebeci = $this->kullanici('muhasebe');

        /* Kapalıyken izin yok. */
        $this->assertFalse($istemci->hesapAcilabilirMi($muhasebeci));

        $istemci->forceFill(['otomatik_hesap' => true])->save();
        $this->assertTrue($istemci->fresh()->hesapAcilabilirMi($muhasebeci));

        /* Erişemeyen birinin hesabı HİÇBİR koşulda açılmaz. */
        $this->assertFalse($istemci->fresh()->hesapAcilabilirMi($this->kullanici('teknik')));
    }

    #[Test]
    public function askidaki_kullanici_hicbir_uygulamaya_giremez(): void
    {
        [$istemci] = $this->istemci();

        $kullanici = $this->kullanici('muhasebe');
        $kullanici->durumuAyarla(Durum::Askida);

        $this->assertFalse($istemci->erisebilirMi($kullanici->fresh()));
    }
}
