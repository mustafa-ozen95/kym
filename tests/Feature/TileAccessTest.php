<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kutucuk görünürlüğü ve kapı.
 *
 * Bazı sistemlerin VARLIĞI bile herkesi ilgilendirmiyor: adı, adresi ve hangi
 * kabinde durduğu tek başına bilgidir. Bu yüzden yetkisi olmayan kutucuğu
 * görmez — sönük ya da kilitli değil, HİÇ YOK.
 *
 * Testlerin kilitlediği asıl şey, elemenin SUNUCUDA yapıldığı: "hepsini getir
 * sonra ekranda gizle" güvenlik değildir; kaynak koda bakan adresi yine
 * görürdü. Bir de liste ile kapının AYNI kuralı kullandığı — ayrışsalardı
 * kutucuğu göremeyen sayfayı açabilir ya da tersi olurdu.
 */
final class TileAccessTest extends TestCase
{
    use RefreshDatabase;

    private function kullanici(string $slug = 'muhasebe'): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    /**
     * Adlar TÜRKÇE HARF TAŞIMAZ — bilinçli.
     *
     * Kutucuklar sayfaya `@json` ile gömülüyor ve orada "ç" `\u00e7` olarak
     * yazılıyor; `assertSee('Açık')` hiçbir zaman eşleşmez ve testin
     * `assertDontSee` yarısı da sessizce HER ZAMAN geçerdi.
     */
    private function kutucuk(string $ad, string $gorunurluk): System
    {
        return System::create([
            'ad' => $ad,
            'adres' => 'https://'.Str::slug($ad).'.ornek.test',
            'renk' => '#79c8a4',
            'grup' => 'ic',
            'durum' => 'up',
            'etkin' => true,
            'gorunurluk' => $gorunurluk,
        ]);
    }

    /* ── Anahtar ── */

    /**
     * Anahtar HER YOLDA üretilir.
     *
     * Yalnızca formda türetilseydi tohumlayıcı ve konsol boş anahtarla kayıt
     * açar, o kutucuğun yetkisi hiç oluşmazdı.
     */
    public function test_anahtar_addan_turetilir_ve_benzersizdir(): void
    {
        $ilk = $this->kutucuk('Kabin Yönetimi', System::EVERYONE);
        $ikinci = $this->kutucuk('Kabin Yönetimi', System::EVERYONE);

        $this->assertSame('kabin-yonetimi', $ilk->anahtar);
        $this->assertSame('kabin-yonetimi-2', $ikinci->anahtar);
        $this->assertSame('sistem-erisim.kabin-yonetimi', $ilk->yetenek());
    }

    /* ── Sayfa listesi ── */

    public function test_herkese_acik_kutucuk_oturumsuz_gorunur(): void
    {
        $this->kutucuk('Acik Sistem', System::EVERYONE);

        $this->get(route('quick-access'))->assertOk()->assertSee('Acik Sistem');
    }

    public function test_girisli_kutucuk_oturumsuz_gorunmez(): void
    {
        $this->kutucuk('Kapali Sistem', System::LOGGED_IN);

        $this->get(route('quick-access'))->assertOk()->assertDontSee('Kapali Sistem');

        $this->actingAs($this->kullanici())
            ->get(route('quick-access'))
            ->assertOk()
            ->assertSee('Kapali Sistem');
    }

    /**
     * Yetkisi olmayan kutucuğu HİÇ görmez — adresini de.
     *
     * Sayfa verisi JSON olarak gömülüyor; "ekranda gizle" yaklaşımı adresi
     * kaynak kodda bırakırdı.
     */
    public function test_yetkili_kutucuk_yetkisi_olmayana_hic_gorunmez(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $yanit = $this->actingAs($this->kullanici())->get(route('quick-access'))->assertOk();

        $yanit->assertDontSee('Gizli Panel');
        $yanit->assertDontSee($sistem->adres);
    }

    public function test_yetkisi_olan_kutucugu_gorur(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);
        $kullanici = $this->kullanici();

        UserPermission::create([
            'user_id' => $kullanici->id,
            'yetenek' => $sistem->yetenek(),
            'etki' => UserPermission::ALLOW,
        ]);

        $this->actingAs($kullanici)->get(route('quick-access'))->assertOk()->assertSee('Gizli Panel');
    }

    /** Yönetici her şeyi geçer; kutucuk yetkileri de buna dahil. */
    public function test_yonetici_butun_kutucuklari_gorur(): void
    {
        $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $this->actingAs($this->kullanici('admin'))
            ->get(route('quick-access'))
            ->assertOk()
            ->assertSee('Gizli Panel');
    }

    /** Rol şablonu da kutucuk yetkisi taşıyabilir. */
    public function test_rol_sablonu_kutucuk_yetkisi_tasiyabilir(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        Role::firstWhere('slug', 'network')->update(['template' => [$sistem->yetenek()]]);

        $this->actingAs($this->kullanici('network'))
            ->get(route('quick-access'))
            ->assertOk()
            ->assertSee('Gizli Panel');
    }

    /** Etkin olmayan kutucuk yetkili birine de görünmez. */
    public function test_etkin_olmayan_kutucuk_yetkiliye_de_gorunmez(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);
        $sistem->update(['etkin' => false]);

        $this->actingAs($this->kullanici('admin'))
            ->get(route('quick-access'))
            ->assertOk()
            ->assertDontSee('Gizli Panel');
    }

    /* ── Kapı ── */

    /**
     * Kapı, oturumsuz isteğe 401 döner — YÖNLENDİRMEZ.
     *
     * nginx `auth_request` 2xx/401/403 dışındaki her yanıtı 500 sayıyor;
     * `auth` ara katmanı altına alınsaydı giriş sayfasına 302 dönerdi.
     */
    public function test_kapi_oturumsuz_istege_401_doner(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $this->get(route('gate', $sistem->anahtar))->assertUnauthorized();
    }

    public function test_kapi_yetkisize_403_doner(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $this->actingAs($this->kullanici())
            ->get(route('gate', $sistem->anahtar))
            ->assertForbidden();
    }

    public function test_kapi_yetkiliye_200_ve_kimlik_basliklari_doner(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);
        $kullanici = $this->kullanici('admin');

        $this->actingAs($kullanici)
            ->get(route('gate', $sistem->anahtar))
            ->assertOk()
            ->assertHeader('X-Kimlik-Eposta', $kullanici->email)
            ->assertHeader('X-Kimlik-Kullanici', $kullanici->uuid);
    }

    /**
     * Bilinmeyen anahtar da 403 — 404 DEĞİL.
     *
     * 404 ile 403 ayrımı "böyle bir kutucuk var" derdi; kapı hangi
     * kutucukların var olduğunu ele vermemeli.
     */
    public function test_kapi_bilinmeyen_anahtarda_403_doner(): void
    {
        $this->actingAs($this->kullanici('admin'))
            ->get(route('gate', 'boyle-bir-sey-yok'))
            ->assertForbidden();
    }

    /** Kapı ile liste AYNI kuralı kullanır; ayrışsalardı biri diğerini yalanlardı. */
    public function test_kapi_ile_liste_ayni_yaniti_verir(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);
        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)->get(route('quick-access'))->assertDontSee('Gizli Panel');
        $this->actingAs($kullanici)->get(route('gate', $sistem->anahtar))->assertForbidden();

        UserPermission::create([
            'user_id' => $kullanici->id, 'yetenek' => $sistem->yetenek(), 'etki' => UserPermission::ALLOW,
        ]);

        $this->actingAs($kullanici)->get(route('quick-access'))->assertSee('Gizli Panel');
        $this->actingAs($kullanici)->get(route('gate', $sistem->anahtar))->assertOk();
    }

    /* ── Yönetim ── */

    /** Yetki, kutucuk eklenince rol ekranında KENDİLİĞİNDEN belirir. */
    public function test_kutucuk_yetkisi_rol_ekraninda_belirir(): void
    {
        $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $this->actingAs($this->kullanici('admin'))
            ->get(route('admin.roles.show', Role::firstWhere('slug', 'network')))
            ->assertOk()
            ->assertSee('Gizli Panel')
            ->assertSee('sistem-erisim.gizli-panel');
    }

    /** Herkese açık kutucuğun yetkisi listelenmez: seçilecek bir şey yok. */
    public function test_acik_kutucugun_yetkisi_listelenmez(): void
    {
        $this->kutucuk('Acik Sistem', System::EVERYONE);

        $this->actingAs($this->kullanici('admin'))
            ->get(route('admin.roles.show', Role::firstWhere('slug', 'network')))
            ->assertOk()
            ->assertDontSee('sistem-erisim.acik-sistem');
    }

    /**
     * Alan GELMEZSE mevcut görünürlük korunur.
     *
     * `nullable` olsaydı bu alanı taşımayan bir gönderim "yetkili" bir
     * kutucuğu sessizce herkese açardı.
     */
    public function test_gorunurluk_gonderilmezse_degismez(): void
    {
        $sistem = $this->kutucuk('Gizli Panel', System::AUTHORIZED);

        $this->actingAs($this->kullanici('admin'))
            ->put(route('admin.systems.update', $sistem), [
                'ad' => 'Gizli Panel',
                'renk' => '#79c8a4',
                'grup' => 'ic',
                'durum' => 'up',
            ])
            ->assertRedirect();

        $this->assertSame(System::AUTHORIZED, $sistem->fresh()->gorunurluk);
    }
}
