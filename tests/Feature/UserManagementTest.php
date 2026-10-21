<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\RegistrationOpen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hesap açma, düzenleme ve kayıt kapısı.
 *
 * İKİ YOL, AYNI SONUÇ: hesabı ya kişi kendisi açar ya yönetici. Testlerin
 * kilitlediği şey ikisinin FARKI — kendi açılan hesap onay bekler (ortada
 * kefil yok), yöneticinin açtığı etkin doğar (kaydı bir insan açıyor).
 *
 * Bir de yetkinin ALAN ALAN sorulduğu: bilgileri düzenlemek, durumu
 * değiştirmek ve rol atamak ayrı işler; biri diğerini kapsamıyor.
 */
final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function rolde(string $slug): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    /** @param  array<string, mixed>  $ustune */
    private function form(array $ustune = []): array
    {
        return [
            'name' => 'Yeni Kisi',
            'email' => 'yeni@ornek.test',
            'password' => 'cok-gizli-1616',
            'password_confirmation' => 'cok-gizli-1616',
            ...$ustune,
        ];
    }

    /* ── Kayıt kapısı ── */

    public function test_kayit_varsayilan_olarak_aciktir(): void
    {
        $this->assertTrue(app(RegistrationOpen::class)->acikMi());

        $this->get(route('hizli-erisim'))->assertOk()->assertSee('data-pencere-ac="kayit"', false);
    }

    /**
     * Kapalıyken UÇ DA kapanır.
     *
     * Yalnızca bağlantıyı gizlemek kapatmak değildir: adresi bilen biri yine
     * hesap açabilirdi.
     */
    public function test_kayit_kapaliyken_uc_de_kapanir(): void
    {
        Setting::yaz(RegistrationOpen::AYAR, false);

        $this->get(route('hizli-erisim'))
            ->assertOk()
            ->assertDontSee('data-pencere-ac="kayit"', false)
            ->assertSee('sistem yöneticinize başvurun');

        $this->post(route('kayit'), $this->form())->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'yeni@ornek.test']);
    }

    /* ── Yöneticinin açtığı hesap ── */

    /**
     * Yöneticinin açtığı hesap ETKİN doğar.
     *
     * Kaydı bir insan açıyor ve o kişi adresin sahibine kefil oluyor. Kendi
     * kendine açılanın beklemede doğması ise bilinçli — orada kefil yok.
     */
    public function test_yoneticinin_actigi_hesap_etkin_dogar(): void
    {
        $this->actingAs($this->rolde('admin'))
            ->post(route('yonetim.kullanicilar.ekle'), $this->form())
            ->assertRedirect();

        $yeni = User::firstWhere('email', 'yeni@ornek.test');

        $this->assertSame(Status::Etkin, $yeni->durum);
        $this->assertNotNull($yeni->email_verified_at, 'Onayı veren kişi adrese kefil.');
    }

    public function test_yoneticinin_actigi_hesap_giris_yapabilir(): void
    {
        $this->actingAs($this->rolde('admin'))->post(route('yonetim.kullanicilar.ekle'), $this->form());

        $this->post(route('giris'), ['email' => 'yeni@ornek.test', 'password' => 'cok-gizli-1616']);

        $this->assertAuthenticated();
    }

    public function test_hesap_acmak_ayri_yetki_ister(): void
    {
        $this->actingAs($this->rolde('network'))
            ->post(route('yonetim.kullanicilar.ekle'), $this->form())
            ->assertForbidden();
    }

    /* ── Düzenleme ── */

    public function test_yonetici_ad_ve_epostayi_duzenler(): void
    {
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => 'Yeni Ad',
                'email' => 'yeniadres@ornek.test',
            ])
            ->assertRedirect();

        $hedef->refresh();

        $this->assertSame('Yeni Ad', $hedef->name);
        $this->assertSame('yeniadres@ornek.test', $hedef->email);
    }

    /**
     * BOŞ PAROLA "değiştirme" demektir.
     *
     * Zorunlu olsaydı, yalnızca adını düzeltmek isteyen yönetici her seferinde
     * kullanıcının parolasını sıfırlamak zorunda kalırdı.
     */
    public function test_bos_parola_eskisini_korur(): void
    {
        $hedef = User::factory()->rol('muhasebe')->create([
            'durum' => Status::Etkin,
            'password' => Hash::make('eski-parola-1616'),
        ]);

        $eski = $hedef->password;

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => 'Yeni Ad',
                'email' => $hedef->email,
                'password' => '',
            ]);

        $this->assertSame($eski, $hedef->fresh()->password);
    }

    public function test_parola_verilirse_degisir(): void
    {
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => $hedef->name,
                'email' => $hedef->email,
                'password' => 'yeni-parola-1616',
                'password_confirmation' => 'yeni-parola-1616',
            ]);

        $this->assertTrue(Hash::check('yeni-parola-1616', $hedef->fresh()->password));
    }

    /* ── Alan alan yetki ── */

    /**
     * Durumu değiştirebilen, BİLGİLERİ düzenleyemez.
     *
     * Alan sessizce yok sayılmaz, istek reddedilir: yok sayılsaydı gövdeye
     * alan ekleyen biri denemeye devam eder ve bir gün kontrol unutulduğunda
     * işe yarardı.
     */
    public function test_onay_yetkisi_bilgi_duzenlemeyi_kapsamaz(): void
    {
        /* Düzenek sınanıyor: varsayılanda dağıtım kapalı (bkz. TestCase). */
        $this->yetkiDagitimiAc();

        $sorumlu = $this->rolde('muhasebe');

        UserPermission::create([
            'user_id' => $sorumlu->id, 'yetenek' => 'kullanici.gor', 'etki' => UserPermission::IZIN,
        ]);
        UserPermission::create([
            'user_id' => $sorumlu->id, 'yetenek' => 'kullanici.onayla', 'etki' => UserPermission::IZIN,
        ]);

        $hedef = $this->rolde('muhasebe');

        /* Durum değişikliği geçer. */
        $this->actingAs($sorumlu)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['durum' => Status::Askida->value])
            ->assertRedirect();

        $this->assertSame(Status::Askida, $hedef->fresh()->durum);

        /* Ad değişikliği REDDEDİLİR. */
        $this->actingAs($sorumlu)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['name' => 'Sızma'])
            ->assertForbidden();

        $this->assertNotSame('Sızma', $hedef->fresh()->name);
    }

    /** Bilgileri düzenleyebilen, rol ATAYAMAZ. */
    public function test_duzenleme_yetkisi_rol_atamayi_kapsamaz(): void
    {
        $sorumlu = $this->rolde('muhasebe');

        UserPermission::create([
            'user_id' => $sorumlu->id, 'yetenek' => 'kullanici.duzenle', 'etki' => UserPermission::IZIN,
        ]);

        $hedef = $this->rolde('muhasebe');

        $this->actingAs($sorumlu)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => 'Yeni Ad',
                'email' => $hedef->email,
                'rol' => Role::firstWhere('is_admin', true)->uuid,
            ])
            ->assertForbidden();

        $this->assertFalse($hedef->fresh()->rol->is_admin);
    }

    /**
     * KENDİ durumunu ve rolünü değiştiremezsin.
     *
     * "Kullanıcıları yönet" yetkisi kendini düşürme ya da paneli sahipsiz
     * bırakma yetkisi değildir. Adını düzeltmek serbest.
     */
    public function test_kendi_durumunu_degistiremez_ama_adini_duzeltebilir(): void
    {
        $yonetici = $this->rolde('admin');

        $this->actingAs($yonetici)
            ->put(route('yonetim.kullanicilar.guncelle', $yonetici), [
                'name' => 'Kendi Yeni Adim',
                'email' => $yonetici->email,
                'durum' => Status::Askida->value,
            ])
            ->assertRedirect();

        $yonetici->refresh();

        $this->assertSame('Kendi Yeni Adim', $yonetici->name);
        $this->assertSame(Status::Etkin, $yonetici->durum, 'Kendi durumunu düşürememeli.');
    }

    /** Aynı e-posta iki hesapta olamaz; eşleşme tahmine dönüşmesin. */
    public function test_ayni_eposta_iki_hesaba_verilemez(): void
    {
        $var = $this->rolde('muhasebe');
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'name' => $hedef->name,
                'email' => $var->email,
            ])
            ->assertSessionHasErrors('email');
    }
}
