<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Permission\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yetkilendirme.
 *
 * MERKEZ YETKİ DAĞITMAZ: burada yanıtlanan tek soru "merkezin KENDİSİNİ kim
 * yönetir". Yetkiler ekran ve işlem başınadır: hesapları onaylayacak birine
 * posta sunucusunun parolasını değiştirme yetkisi de vermek gerekmez.
 *
 * Testlerin kilitlediği asıl şey, yetkinin GENİŞLEMEDİĞİ: bir ekrana girmek
 * başka bir ekranı açmıyor, hesabı onaylamak rol atamaya yetmiyor ve kimse
 * kendi yetkisini büyütemiyor.
 */
final class PermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BU SINIF YETKİ DAĞITIM DÜZENEĞİNİ SINAR.
     *
     * Varsayılanda dağıtım kapalı — merkezin yönetimi yalnızca yöneticide. Kapalı
     * hâlin kendisi ayrıca sınanıyor (test_teknik_yonetime_hic_giremez ve
     * test_yonetim_yalnizca_yoneticide). Gerekçe TestCase'te.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->yetkiDagitimiAc();
    }

    private function rolde(string $slug): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    /**
     * Hesapları yönetebilen ama ROL ATAYAMAYAN kişi.
     *
     * Yerleşik rollerin hiçbiri bu yetkileri taşımıyor (merkezin yönetimi
     * yalnızca yöneticidedir); kişisel istisnayla kurulur — zaten sınanan şey
     * de bu ayrımın kendisi.
     */
    private function hesapSorumlusu(): User
    {
        $kullanici = $this->rolde('muhasebe');

        foreach (['kullanici.gor', 'kullanici.onayla'] as $yetenek) {
            UserPermission::create([
                'user_id' => $kullanici->id, 'yetenek' => $yetenek, 'etki' => UserPermission::IZIN,
            ]);
        }

        return $kullanici;
    }

    /* ── Varsayılan hâl: yönetim yalnızca yöneticide ── */

    /**
     * AYAR AÇIKKEN ŞABLON DA İSTİSNA DA YÖNETİM AÇMAZ.
     *
     * Yalnızca rol şablonlarını boşaltmak bu kuralı VERİYE yazardı; bir kutucuk işaretlenince sessizce
     * geri açılırdı. Test tam bunu kurar: şablon da kişisel izin de dolu,
     * yine de kapı kapalı.
     */
    public function test_yonetim_yalnizca_yoneticide(): void
    {
        config(['permissions.yonetim_yalnizca_yonetici' => true]);

        Role::where('slug', 'network')->first()->update(['template' => ['sistem.gor', 'grup.gor']]);

        $kullanici = $this->rolde('network');

        UserPermission::create([
            'user_id' => $kullanici->id, 'yetenek' => 'kullanici.gor', 'etki' => UserPermission::IZIN,
        ]);

        foreach (['sistem.gor', 'grup.gor', 'kullanici.gor', 'ayar.duzenle', 'rol.duzenle'] as $yetki) {
            $this->assertFalse($kullanici->can($yetki), "Yönetici olmayan {$yetki} yapamamalı.");
        }

        $this->assertFalse($kullanici->can('yonet'), 'Yönetim kapısı kapalı olmalı.');

        $this->actingAs($kullanici)->get(route('yonetim.sistemler'))->assertForbidden();
        $this->actingAs($kullanici)->get(route('yonetim.kullanicilar'))->assertForbidden();
    }

    /**
     * KUTUCUK ERİŞİMİ BUNDAN ETKİLENMEZ.
     *
     * sistem-erisim.* ve grup-erisim.* yetkileri Hızlı Erişim sayfasının
     * GÖRÜNÜRLÜĞÜDÜR, yönetim değil. Yönetimi kapatan kural "yetkili"
     * kutucukları kimseye açılamaz hâle getirmemeli.
     */
    public function test_yonetim_kapaliyken_kutucuk_erisimi_calisir(): void
    {
        config(['permissions.yonetim_yalnizca_yonetici' => true]);

        $kutucuk = System::create([
            'ad' => 'Gizli Pano', 'adres' => 'https://ornek.test', 'grup' => 'ic',
            'renk' => '#79c8a4', 'durum' => 'up',
            'gorunurluk' => System::YETKILI, 'etkin' => true,
        ]);

        $yetki = $kutucuk->yetenek();

        Role::where('slug', 'network')->first()->update(['template' => [$yetki]]);

        $kullanici = $this->rolde('network');

        /*
         * Kutucuk yetkisi Gate'e KAYDEDİLMEZ (kayıtlardan türüyor, uygulama
         * açılışında tablo henüz olmayabilir); doğrudan çözümleyiciye
         * sorulur — kutucuğun kendi kuralı da öyle yapıyor.
         */
        $this->assertTrue(
            app(PermissionResolver::class)->yapabilirMi($kullanici, $yetki),
            'Kutucuk erişimi verilebilmeli.',
        );

        $this->assertTrue($kutucuk->gorebilirMi($kullanici), 'Kutucuk sayfada görünmeli.');
        $this->assertFalse($kullanici->can('yonet'), 'Kutucuk erişimi yönetim kapısını AÇMAMALI.');
    }

    /* ── Şablonlar ── */

    public function test_yerlesik_roller_kurulumla_gelir(): void
    {
        /*
         * Adlar bağlı panellerle AYNI: rol bir kişiyi tanımlıyor ve o kişi
         * panelden panele farklı rol adları görmemeli.
         */
        $this->assertSame(
            ['admin', 'network', 'teknik', 'muhasebe'],
            Role::sirali()->pluck('slug')->all(),
        );

        $this->assertTrue(Role::firstWhere('slug', 'admin')->is_admin);
        $this->assertTrue(Role::firstWhere('slug', 'teknik')->is_default);
    }

    public function test_yonetici_her_seyi_yapar(): void
    {
        $yonetici = $this->rolde('admin');

        $this->assertTrue($yonetici->can('sistem.sil'));
        $this->assertTrue($yonetici->can('ayar.posta-dene'));
        $this->assertTrue($yonetici->can('kullanici.rol'));
    }

    /**
     * NETWORK DE GÖRÜR, DEĞİŞTİRMEZ.
     *
     * Merkezi DEĞİŞTİRMEK yalnızca yöneticide. Bir kişiye yazma gerekiyorsa kişisel
     * istisnayla verilir, rolün tamamına değil.
     */
    public function test_network_gorur_degistirmez(): void
    {
        $network = $this->rolde('network');

        $this->assertTrue($network->can('sistem.gor'));
        $this->assertTrue($network->can('grup.gor'));

        $this->assertFalse($network->can('sistem.ekle'));
        $this->assertFalse($network->can('sistem.duzenle'));
        $this->assertFalse($network->can('sistem.sil'));
        $this->assertFalse($network->can('grup.ekle'));
        $this->assertFalse($network->can('grup.duzenle'));
        $this->assertFalse($network->can('grup.sil'));

        $this->assertFalse($network->can('kullanici.gor'));
        $this->assertFalse($network->can('ayar.gor'));
        $this->assertFalse($network->can('rol.duzenle'));
    }

    /**
     * YAZMA UCU DA KAPALI.
     *
     * Yalnızca düğmeyi gizlemek yetmez: adresi bilen biri yine gönderirdi.
     */
    public function test_network_kutucuk_ekleyemez(): void
    {
        $this->actingAs($this->rolde('network'))
            ->post(route('yonetim.sistemler.ekle'), ['ad' => 'Kaçak', 'adres' => 'https://ornek.test'])
            ->assertForbidden();
    }

    /**
     * TEKNİK merkezin yönetimine HİÇ GİRMEZ.
     *
     * Kural: teknik yönetim ekranlarının hiçbirini açmaz, hiçbir şey ekleyip
     * silip düzenlemez; salt okuma da /yonetim kapısını açacağı için verilmez.
     *
     * Kapının kendisi de sınanıyor: yetki listesi boşalınca `yonet` geçidi
     * kapanmalı, yoksa boş bir ekrana girilirdi.
     */
    public function test_teknik_yonetime_hic_giremez(): void
    {
        $teknik = $this->rolde('teknik');

        foreach (['sistem.gor', 'grup.gor', 'sistem.duzenle', 'grup.ekle'] as $yetki) {
            $this->assertFalse($teknik->can($yetki), "teknik {$yetki} yapamamalı.");
        }

        $this->assertFalse($teknik->can('yonet'), 'Yönetim kapısı kapalı olmalı.');
    }

    /**
     * ONAYLAMAK, ROL VERMEK DEĞİLDİR.
     *
     * Kapsasaydı hesapları onaylayan kişi kendine yönetici rolü verebilirdi.
     */
    public function test_onaylayan_rol_atayamaz(): void
    {
        $sorumlu = $this->hesapSorumlusu();

        $this->assertTrue($sorumlu->can('kullanici.onayla'));
        $this->assertFalse($sorumlu->can('kullanici.rol'));
    }

    public function test_muhasebe_hicbir_yonetim_ekranina_giremez(): void
    {
        $muhasebe = $this->rolde('muhasebe');

        $this->assertFalse($muhasebe->can('yonet'));
        $this->assertFalse($muhasebe->can('sistem.gor'));

        $this->actingAs($muhasebe)->get(route('yonetim.sistemler'))->assertForbidden();
    }

    /* ── Kişisel istisnalar ── */

    public function test_kisisel_izin_sablonun_ustune_ekler(): void
    {
        $editor = $this->rolde('network');

        UserPermission::create([
            'user_id' => $editor->id, 'yetenek' => 'kullanici.gor', 'etki' => UserPermission::IZIN,
        ]);

        $this->assertTrue($editor->can('kullanici.gor'));
        $this->assertTrue($editor->can('sistem.gor'), 'Şablon yerinde kalmalı.');
    }

    /**
     * Kişisel RED, şablonun verdiğini geri alır.
     *
     * Bunun ayrı durması gerekiyor: şablondan bir yetkiyi tek kişi için
     * çıkarmanın başka yolu yok — yeni bir rol açmak dışında.
     */
    public function test_kisisel_red_sablonun_verdigini_geri_alir(): void
    {
        $editor = $this->rolde('network');

        UserPermission::create([
            'user_id' => $editor->id, 'yetenek' => 'sistem.gor', 'etki' => UserPermission::RED,
        ]);

        $this->assertFalse($editor->can('sistem.gor'));
        $this->assertTrue($editor->can('grup.gor'));
    }

    /** Yönetici rolü kişisel redle bile kısıtlanmaz; kısıtlansaydı "her şeyi geçer" yalan olurdu. */
    public function test_yoneticiyi_kisisel_red_durdurmaz(): void
    {
        $yonetici = $this->rolde('admin');

        UserPermission::create([
            'user_id' => $yonetici->id, 'yetenek' => 'sistem.sil', 'etki' => UserPermission::RED,
        ]);

        $this->assertTrue($yonetici->can('sistem.sil'));
    }

    /* ── Ekranlar ── */

    public function test_yetkisi_olmayan_uc_403_doner(): void
    {
        $editor = $this->rolde('network');

        $this->actingAs($editor)->get(route('yonetim.kullanicilar'))->assertForbidden();
        $this->actingAs($editor)->get(route('yonetim.ayarlar'))->assertForbidden();
        $this->actingAs($editor)->get(route('yonetim.sistemler'))->assertOk();
    }

    /**
     * Menüde YALNIZCA açılabilen bölümler durur.
     *
     * Açamayacağı bir bağlantıyı göstermek, kullanıcıya yapabileceği bir
     * şeymiş gibi anlatmaktır.
     */
    public function test_menude_yalnizca_acilabilen_bolumler_gorunur(): void
    {
        $yanit = $this->actingAs($this->rolde('network'))->get(route('yonetim.sistemler'));

        $yanit->assertOk()
            ->assertSee('Sistemler')
            ->assertDontSee('>Kullanıcılar<', false)
            ->assertDontSee('>Ayarlar<', false)
            ->assertDontSee('>Roller<', false);
    }

    /**
     * ROL ALANI SESSİZCE YOK SAYILMAZ, İSTEK REDDEDİLİR.
     *
     * Yok sayılsaydı gövdeye alan ekleyen biri denemeye devam eder ve bir gün
     * kontrol unutulduğunda işe yarardı; 403 ise denemeyi kayda düşürür.
     */
    public function test_rol_yetkisi_olmayan_gövdeye_rol_ekleyemez(): void
    {
        $sorumlu = $this->hesapSorumlusu();
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($sorumlu)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), [
                'durum' => Status::Etkin->value,
                'rol' => Role::firstWhere('is_admin', true)->uuid,
            ])
            ->assertForbidden();

        $this->assertFalse($hedef->fresh()->rol->is_admin);
    }

    /** Durum değişikliği rol yetkisi olmadan da geçer: istenen zaten budur. */
    public function test_rolsuz_gonderim_durumu_gunceller(): void
    {
        $sorumlu = $this->hesapSorumlusu();
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($sorumlu)
            ->put(route('yonetim.kullanicilar.guncelle', $hedef), ['durum' => Status::Askida->value])
            ->assertRedirect();

        $this->assertSame(Status::Askida, $hedef->fresh()->durum);
    }

    /* ── Rol ekranı ── */

    public function test_yonetici_rol_sablonunu_degistirir(): void
    {
        $rol = Role::firstWhere('slug', 'network');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.roller.guncelle', $rol), [
                'slug' => 'network', 'label' => 'Network', 'badge' => 'blue',
                'template' => ['sistem.gor', 'kullanici.gor'],
            ])
            ->assertRedirect();

        $this->assertSame(['sistem.gor', 'kullanici.gor'], $rol->fresh()->yetenekler());
    }

    /**
     * TANINMAYAN YETENEK KAYDEDİLMEZ.
     *
     * Gövdeye elle satır ekleyen biri, bir gün gerçek bir yetkiye denk
     * gelecek bir ad bırakabilirdi.
     */
    public function test_taninmayan_yetenek_sablona_yazilmaz(): void
    {
        $rol = Role::firstWhere('slug', 'network');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.roller.guncelle', $rol), [
                'slug' => 'network', 'label' => 'Network', 'badge' => 'blue',
                'template' => ['sistem.gor', 'uydurma.yetki'],
            ]);

        $this->assertSame(['sistem.gor'], $rol->fresh()->yetenekler());
    }

    /** Son yönetici rolü düşürülemez; düşürülseydi paneli yönetecek kimse kalmazdı. */
    public function test_son_yonetici_rolu_dusurulemez(): void
    {
        $rol = Role::firstWhere('slug', 'admin');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.roller.guncelle', $rol), [
                'slug' => 'admin', 'label' => 'Yönetici', 'badge' => 'purple',
            ])
            ->assertSessionHas('error');

        $this->assertTrue($rol->fresh()->is_admin);
    }

    public function test_yerlesik_rol_silinemez(): void
    {
        $rol = Role::firstWhere('slug', 'network');

        $this->actingAs($this->rolde('admin'))
            ->delete(route('yonetim.roller.sil', $rol))
            ->assertSessionHas('error');

        $this->assertModelExists($rol);
    }

    public function test_kullanicisi_olan_rol_silinemez(): void
    {
        $rol = Role::create(['slug' => 'gecici', 'label' => 'Geçici', 'badge' => 'gray', 'template' => []]);
        User::factory()->create(['rol_id' => $rol->id]);

        $this->actingAs($this->rolde('admin'))
            ->delete(route('yonetim.roller.sil', $rol))
            ->assertSessionHas('error');

        $this->assertModelExists($rol);
    }

    /** Varsayılan rol TEK olur; yoksa yeni kullanıcının hangisini alacağı belirsizdir. */
    public function test_varsayilan_rol_tektir(): void
    {
        $editor = Role::firstWhere('slug', 'network');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.roller.guncelle', $editor), [
                'slug' => 'network', 'label' => 'Network', 'badge' => 'blue', 'is_default' => '1',
            ]);

        $this->assertSame(1, Role::where('is_default', true)->count());
        $this->assertTrue($editor->fresh()->is_default);
    }

    /**
     * YÖNETİCİ HER YÖNETİM EKRANINI AÇABİLİR.
     *
     * Bu test olmadan bir ekran sessizce kırılabilir: başka hiçbir test o
     * sayfayı açmıyorsa takım yeşilken ekran 500 dönebilir.
     *
     * Liste elle tutulur: yeni bir yönetim ekranı eklendiğinde buraya da
     * eklenmesi gerekir ve o an "bu ekran gerçekten açılıyor mu" sorusu bir
     * kez sorulmuş olur.
     */
    public function test_butun_yonetim_ekranlari_acilir(): void
    {
        $yonetici = $this->rolde('admin');

        foreach (['yonetim.sistemler', 'yonetim.kullanicilar', 'yonetim.roller', 'yonetim.ayarlar', 'yonetim.istemciler'] as $rota) {
            $this->actingAs($yonetici)->get(route($rota))->assertOk();
        }

        /* Rol AYRINTISI ayrı bir sayfa; listeyle birlikte kırılmasın. */
        $this->actingAs($yonetici)
            ->get(route('yonetim.roller.goster', Role::firstWhere('slug', 'network')))
            ->assertOk();
    }

    /**
     * ROLLER, AYARLAR'IN SEKMESİ.
     *
     * Ayrı bir üst menü öğesi olmaz: kullanıcı onu Ayarlar'ın altında arar.
     */
    public function test_roller_ayarlarin_sekmesidir(): void
    {
        $yonetici = $this->rolde('admin');

        $this->assertStringContainsString('/yonetim/ayarlar/roller', route('yonetim.roller'));

        /* Şerit iki sayfada da aynı: Genel ve Roller. */
        foreach (['yonetim.ayarlar', 'yonetim.roller'] as $rota) {
            $this->actingAs($yonetici)->get(route($rota))
                ->assertOk()
                ->assertSee(route('yonetim.ayarlar'))
                ->assertSee(route('yonetim.roller'));
        }

        /* Üst menüde artık yok. */
        $this->actingAs($yonetici)->get(route('yonetim.sistemler'))
            ->assertOk()
            ->assertDontSee('>Roller<', false);
    }

    /**
     * Yalnızca rol yetkisi olan da Ayarlar'a girebilmeli.
     *
     * Kapı "ayar.gor" olsaydı, rolleri düzenleyebilen ama ayarları
     * göremeyen biri sekmesine hiç ulaşamazdı.
     */
    public function test_yalnizca_rol_yetkisi_olan_da_ayarlara_girer(): void
    {
        $kullanici = $this->rolde('muhasebe');

        UserPermission::create([
            'user_id' => $kullanici->id, 'yetenek' => 'rol.gor', 'etki' => UserPermission::IZIN,
        ]);

        $this->actingAs($kullanici)->get(route('yonetim.roller'))
            ->assertOk()
            ->assertSee('Roller')
            /* Şeritte yalnızca açabildiği sekme durur. */
            ->assertDontSee('>Genel<', false);

        /* Genel sekmesi görünmez ve açılmaz: o ayrı bir yetki. */
        $this->actingAs($kullanici)->get(route('yonetim.ayarlar'))->assertForbidden();
    }

    /* ── Kişisel yetki ekranı ── */

    public function test_kendi_yetkini_degistiremezsin(): void
    {
        $yonetici = $this->rolde('admin');

        $this->actingAs($yonetici)
            ->put(route('yonetim.kullanicilar.yetkiler', $yonetici), [
                'yetkiler' => ['sistem.sil' => 'red'],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('kullanici_yetkileri', 0);
    }

    public function test_bos_secim_istisna_birakmaz(): void
    {
        $hedef = $this->rolde('network');

        $this->actingAs($this->rolde('admin'))
            ->put(route('yonetim.kullanicilar.yetkiler', $hedef), [
                'yetkiler' => ['sistem.sil' => '', 'kullanici.gor' => 'izin'],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('kullanici_yetkileri', 1);
        $this->assertDatabaseHas('kullanici_yetkileri', ['yetenek' => 'kullanici.gor', 'etki' => 'izin']);
    }

    /** Yetki dağıtmak, rol atamakla AYNI ağırlıkta bir iştir ve aynı yetkiyi ister. */
    public function test_yetki_dagitmak_rol_yetkisi_ister(): void
    {
        $this->actingAs($this->hesapSorumlusu())
            ->put(route('yonetim.kullanicilar.yetkiler', $this->rolde('muhasebe')), [
                'yetkiler' => ['sistem.sil' => 'izin'],
            ])
            ->assertForbidden();
    }
}
