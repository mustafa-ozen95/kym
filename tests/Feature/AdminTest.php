<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Yönetim ekranları.
 *
 * Kutucukları koddan çıkarmanın asıl karşılığı bu ekran: bir sistem eklemek
 * artık dosya düzenlemeyi değil, formu doldurmayı gerektiriyor.
 *
 * Kapı iki kere kilitli: rotalar `auth` altında, controller'lar `can:yonet`
 * diyor. Testler ikisini de sınar.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    /* Gerçek bir yönetici ikinci adımı kurmuş olur; kural bunu şart koşuyor. */
    private function yonetici(): User
    {
        return User::factory()->yonetici()->ikiAdimli()->create();
    }

    private function veri(array $ustune = []): array
    {
        return [
            'ad' => 'Yeni Sistem',
            'aciklama' => 'Örnek açıklama',
            'adres' => 'https://yeni.ornek.test',
            'kod' => 'YNI-01',
            'renk' => '#79c8a4',
            'grup' => 'ic',
            'serit' => 's1',
            'durum' => 'up',
            'sira' => 10,
            'etkin' => '1',
            ...$ustune,
        ];
    }

    /** Oturumsuz kullanıcı giriş penceresi açık ana sayfaya gider. */
    #[Test]
    public function oturumsuz_kullanici_girise_yonlendirilir(): void
    {
        $this->get(route('admin.systems'))
            ->assertRedirect(route('quick-access', ['window' => 'login']));
    }

    /**
     * Yönetim yetkisi OLMAYAN giremez; menüde de görmez.
     *
     * Test, yönetim yetkisi olmayanı sınıyor — rol adını değil.
     */
    #[Test]
    public function yetkisiz_kullanici_yonetime_giremez(): void
    {
        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->get(route('admin.systems'))
            ->assertForbidden();
    }

    /** Görme yetkisi olan girer ama DEĞİŞTİREMEZ. */
    #[Test]
    public function network_gorur_ama_degistiremez(): void
    {
        /* Düzenek sınanıyor: varsayılanda dağıtım kapalı (bkz. TestCase). */
        $this->yetkiDagitimiAc();

        $network = User::factory()->rol('network')->create();

        $this->actingAs($network)->get(route('admin.systems'))->assertOk();

        $this->actingAs($network)
            ->post(route('admin.systems.store'), $this->veri())
            ->assertForbidden();
    }

    /**
     * TEKNİK yönetime HİÇ giremez.
     *
     * Hem kapı hem yazma ucu sınanıyor: yalnızca menüyü gizlemek, adresi
     * bilen için hiçbir şey değiştirmezdi.
     */
    #[Test]
    public function teknik_yonetime_hic_giremez(): void
    {
        $teknik = User::factory()->rol('teknik')->create();

        $this->actingAs($teknik)->get(route('admin.systems'))->assertForbidden();
        $this->actingAs($teknik)->get(route('admin.groups'))->assertForbidden();

        $this->actingAs($teknik)
            ->post(route('admin.systems.store'), $this->veri())
            ->assertForbidden();
    }

    #[Test]
    public function yonetici_kutucuk_ekleyebilir(): void
    {
        $this->actingAs($this->yonetici())
            ->post(route('admin.systems.store'), $this->veri())
            ->assertRedirect(route('admin.systems'));

        $this->assertDatabaseHas('sistemler', ['ad' => 'Yeni Sistem', 'grup' => 'ic', 'etkin' => true]);
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'sistem-eklendi']);
    }

    #[Test]
    public function eklenen_kutucuk_hizli_erisim_sayfasinda_gorunur(): void
    {
        $this->actingAs($this->yonetici())->post(route('admin.systems.store'), $this->veri());

        $this->get(route('quick-access'))->assertOk()->assertSee('Yeni Sistem');
    }

    #[Test]
    public function kutucuk_silinince_sayfadan_kalkar(): void
    {
        $sistem = System::create($this->veri(['etkin' => true]));

        $this->actingAs($this->yonetici())
            ->delete(route('admin.systems.destroy', $sistem))
            ->assertRedirect(route('admin.systems'));

        /*
         * Ad artık SİLİNDİ BİLDİRİMİNDE geçiyor; "sayfada hiç görünmesin"
         * demek bildirimi de yasaklamak olurdu. Asıl soru kutucuğun ÇİZİM
         * VERİSİNDEN düşüp düşmediği — sayfa onu o listeden çiziyor.
         */
        $govde = $this->get(route('quick-access'))->assertOk()->getContent();
        $this->assertStringContainsString('id="system-data"', $govde, 'Cizim verisi bulunamadi; asagidaki kontrol bosa duserdi.');

        $veri = str($govde)->after('id="system-data"')->before('</script>')->toString();

        $this->assertStringNotContainsString('Yeni Sistem', $veri);
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'sistem-silindi']);
    }

    /**
     * Grup ve şerit SERBEST METİN DEĞİL.
     *
     * Çizim tarafı yalnızca tanımlı değerleri tanıyor; uydurma bir şerit adı
     * kartı sessizce boş bırakırdı.
     */
    #[Test]
    public function taninmayan_grup_ve_serit_reddedilir(): void
    {
        $this->actingAs($this->yonetici())
            ->post(route('admin.systems.store'), $this->veri(['grup' => 'olmayan', 'serit' => 's99']))
            ->assertSessionHasErrors(['grup', 'serit']);
    }

    #[Test]
    public function yan_baglanti_yarim_birakilamaz(): void
    {
        $this->actingAs($this->yonetici())
            ->post(route('admin.systems.store'), $this->veri(['yan_etiket' => 'Yedekler']))
            ->assertSessionHasErrors('yan_adres');
    }

    #[Test]
    public function yonetici_bekleyen_hesabi_etkinlestirir(): void
    {
        $bekleyen = User::factory()->beklemede()->create();

        $this->actingAs($this->yonetici())
            ->put(route('admin.users.update', $bekleyen), [
                'status' => Status::Etkin->value,
                'role' => Role::firstWhere('slug', 'muhasebe')->uuid,
            ])
            ->assertRedirect();

        $this->assertSame(Status::Etkin, $bekleyen->fresh()->durum);
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'kullanici-guncellendi']);
    }

    /**
     * KENDİ rolünü değiştiremezsin.
     *
     * "Kullanıcıları yönet" yetkisi, kendini düşürüp paneli sahipsiz bırakma
     * ya da kendini yükseltme yetkisi değildir.
     */
    #[Test]
    public function yonetici_kendi_rolunu_degistiremez(): void
    {
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)
            ->put(route('admin.users.update', $yonetici), [
                'status' => Status::Askida->value,
                'role' => Role::firstWhere('slug', 'muhasebe')->uuid,
            ]);

        $yonetici->refresh();

        $this->assertTrue($yonetici->rol->is_admin);
        $this->assertSame(Status::Etkin, $yonetici->durum);
    }

    /** Menüde yalnızca erişilebilen bölümler görünür. */
    #[Test]
    public function yonetim_baglantisi_yalnizca_yetkilide_gorunur(): void
    {
        $this->actingAs($this->yonetici())
            ->get(route('quick-access'))
            ->assertSee(route('admin.systems'), escape: false);

        /* Hiçbir yönetim yetkisi olmayan bağlantıyı hiç görmez. */
        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->get(route('quick-access'))
            ->assertDontSee(route('admin.systems'), escape: false);
    }

    /**
     * İkinci adım ZORUNLU DEĞİL.
     *
     * Varsayılan: özellik duruyor, dayatma yok.
     * Kurmamış bir yönetici de yönetim ekranlarını açabilir.
     */
    #[Test]
    public function iki_adimsiz_yonetici_de_yonetime_girebilir(): void
    {
        $this->actingAs(User::factory()->yonetici()->create())
            ->get(route('admin.systems'))
            ->assertOk();
    }

    /** İkinci adım ekranı her kullanıcıya açık: herkes kendi hesabını kurar. */
    #[Test]
    public function iki_adim_ekrani_her_kullaniciya_acik(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.two-step'))
            ->assertOk();
    }
}
