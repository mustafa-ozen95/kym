<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\ActivityLog;
use App\Models\OauthClient;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * Denetim kaydı.
 *
 * Kaydın işi sonradan "ne olmuştu" sorusuna yanıt vermek; bu yüzden asıl
 * sınanan şey satırların YAZILDIĞI değil, DEĞİŞTİRİLEMEDİĞİ. Her satırın
 * özeti bir öncekini de kapsıyor: bir satırı düzenlemek tek satırı
 * değiştirmekle bitmiyor, zincirin tamamı bozuluyor ve doğrulama bunu görüyor.
 *
 * "Kim nereye giriş yaptı" ayrı bir soru: merkezin kendi giriş kaydı yalnızca
 * "merkeze girdi" der. Hangi projeye gidildiği, istemcinin kimlik sorduğu anda
 * yazılır.
 */
final class AuditTest extends TestCase
{
    use RefreshDatabase;

    private function kullanici(string $slug = 'muhasebe'): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    /* ── "Kim nereye girdi" ── */

    /**
     * Projeye giriş, KİMLİK SORULDUĞU AN yazılır.
     *
     * Bu uç girişin son adımıdır: istemci jetonu alıp "bu jeton kimin" diye
     * sorar ve ancak ondan sonra kendi oturumunu açar.
     */
    public function test_projeye_giris_uygulama_adiyla_kaydedilir(): void
    {
        $kullanici = $this->kullanici();

        /** @var OauthClient $istemci */
        $istemci = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'NYS',
            ['https://panel-b.ornek.test/giris/merkez/geri'],
        );

        /*
         * Jeton ELLE kurulur ve kullanıcıya bağlanır.
         *
         * Gerçek akışta jetonu Passport veriyor; burada sınanan şey jeton
         * üretimi değil, "bu jeton kimin" sorusunun kayda geçmesi. Kişisel
         * erişim istemcisi kurup imzalı JWT üretmek testi kendi konusundan
         * uzaklaştırırdı.
         */
        $jeton = new AccessToken(['oauth_client_id' => $istemci->getKey()]);

        $this->actingAs($kullanici->withAccessToken($jeton), 'api')
            ->getJson(route('kimlik.kullanici'))
            ->assertOk()
            ->assertJsonPath('email', $kullanici->email);

        $this->assertDatabaseHas('activity_logs', [
            'eylem' => 'projeye-giris',
            'ozet' => $kullanici->email.' → NYS',
        ]);
    }

    /* ── Zincir ── */

    public function test_zincir_saglam_kayitta_dogrulanir(): void
    {
        $recorder = app(ActivityRecorder::class);
        $kullanici = $this->kullanici('admin');

        $recorder->record('deneme', 'birinci', $kullanici);
        $recorder->record('deneme', 'ikinci', $kullanici);

        $sonuc = $recorder->verify();

        $this->assertTrue($sonuc['ok']);
        $this->assertNull($sonuc['broken_id']);
    }

    /**
     * Bir satır sonradan düzenlenirse zincir KIRILIR.
     *
     * Testin asıl konusu bu: kayıt silinemiyor diye değil, değiştirilemiyor
     * diye güvenilir.
     */
    public function test_degistirilen_satir_zinciri_kirar(): void
    {
        $recorder = app(ActivityRecorder::class);
        $kullanici = $this->kullanici('admin');

        $recorder->record('deneme', 'birinci', $kullanici);
        $hedef = $recorder->record('deneme', 'ikinci', $kullanici);
        $recorder->record('deneme', 'ucuncu', $kullanici);

        /* Doğrudan veritabanına yazılıyor: model üzerinden geçse de sonuç aynı. */
        ActivityLog::where('id', $hedef->id)->update(['ozet' => 'ikinci (degistirildi)']);

        $sonuc = $recorder->verify();

        $this->assertFalse($sonuc['ok']);
        $this->assertSame($hedef->id, $sonuc['broken_id']);
    }

    /* ── Zincirin ucu: kuyruktan kesme ve boşaltma ── */

    private function ucKayit(): array
    {
        $recorder = app(ActivityRecorder::class);
        $kullanici = $this->kullanici('admin');

        return [
            $recorder,
            $recorder->record('deneme', 'birinci', $kullanici),
            $recorder->record('deneme', 'ikinci', $kullanici),
            $recorder->record('deneme', 'ucuncu', $kullanici),
        ];
    }

    public function test_en_yeni_satirin_silinmesi_gorulur(): void
    {
        [$recorder, , , $son] = $this->ucKayit();
        $this->assertTrue($recorder->verify()['ok']);

        ActivityLog::where('id', $son->id)->delete();

        $sonuc = $recorder->verify();

        $this->assertFalse($sonuc['ok']);
        $this->assertNull($sonuc['broken_id']);
        $this->assertSame(2, $sonuc['checked']);
    }

    public function test_tum_tablonun_bosaltilmasi_gorulur(): void
    {
        [$recorder] = $this->ucKayit();

        ActivityLog::query()->delete();

        $this->assertFalse($recorder->verify()['ok']);
    }

    public function test_kesmeden_sonra_yeni_kayit_kesmeyi_gizlemez(): void
    {
        [$recorder, , , $son] = $this->ucKayit();

        ActivityLog::where('id', $son->id)->delete();
        $recorder->record('deneme', 'kesmeden sonra', $this->kullanici('admin'));

        $this->assertFalse($recorder->verify()['ok'], 'Çapa tutarsızken ilerlememeli; kesme sonradan yasallaşamaz.');
    }

    public function test_capa_imzasi_bozulursa_kirik_sayilir(): void
    {
        [$recorder] = $this->ucKayit();

        DB::table('ayarlar')->where('anahtar', ActivityRecorder::CAPA)
            ->update(['deger' => json_encode(['head' => null, 'n' => 0, 'mac' => 'x'])]);

        $this->assertFalse($recorder->verify()['ok']);
    }

    public function test_normal_kayit_akisi_ve_dogrulama_bozulmaz(): void
    {
        [$recorder] = $this->ucKayit();

        $recorder->record('deneme', 'dorduncu', $this->kullanici('admin'));
        $sonuc = $recorder->verify();

        $this->assertTrue($sonuc['ok']);
        $this->assertSame(4, $sonuc['checked']);
    }

    /**
     * CANLI ZİNCİR: çapa yokken yazılmış satırlar doğrulanmaya devam eder.
     *
     * Çapa göçle değil, ilk kayıtta/doğrulamada mevcut kuyruktan kurulur;
     * canlıda hiçbir şey elle yapılmaz.
     */
    public function test_capasiz_eski_zincir_dogrulanir_ve_sonra_capalanir(): void
    {
        [$recorder, , , $son] = $this->ucKayit();
        DB::table('ayarlar')->where('anahtar', ActivityRecorder::CAPA)->delete();

        /* İlk doğrulama çapayı kurar. */
        $this->assertTrue($recorder->verify()['ok']);
        $this->assertDatabaseHas('ayarlar', ['anahtar' => ActivityRecorder::CAPA]);

        /* Kurulduktan SONRAki kesme görülür. */
        ActivityLog::where('id', $son->id)->delete();
        $this->assertFalse($recorder->verify()['ok']);
    }

    public function test_capasiz_eski_zincire_kayit_eklenince_capa_kurulur(): void
    {
        [$recorder] = $this->ucKayit();
        DB::table('ayarlar')->where('anahtar', ActivityRecorder::CAPA)->delete();

        $yeni = $recorder->record('deneme', 'capadan sonra', $this->kullanici('admin'));
        $this->assertTrue($recorder->verify()['ok']);

        ActivityLog::where('id', $yeni->id)->delete();
        $this->assertFalse($recorder->verify()['ok']);
    }

    /* ── Ekran ── */

    public function test_ekran_kayitlari_listeler(): void
    {
        $yonetici = $this->kullanici('admin');

        app(ActivityRecorder::class)->record('deneme', 'gorunur olmali', $yonetici);

        $this->actingAs($yonetici)
            ->get(route('yonetim.kayitlar'))
            ->assertOk()
            ->assertSee('gorunur olmali')
            ->assertSee('Denetim kaydı');
    }

    public function test_eyleme_gore_suzulur(): void
    {
        $yonetici = $this->kullanici('admin');
        $recorder = app(ActivityRecorder::class);

        $recorder->record('birinci-eylem', 'aranan satir', $yonetici);
        $recorder->record('ikinci-eylem', 'baska satir', $yonetici);

        $this->actingAs($yonetici)
            ->get(route('yonetim.kayitlar', ['eylem' => 'birinci-eylem']))
            ->assertOk()
            ->assertSee('aranan satir')
            ->assertDontSee('baska satir');
    }

    public function test_dogrulama_sonucu_kayda_da_yazilir(): void
    {
        $yonetici = $this->kullanici('admin');

        $this->actingAs($yonetici)
            ->post(route('yonetim.kayitlar.dogrula'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('activity_logs', ['eylem' => 'kayit-dogrulandi']);
    }

    /** Kırık zincir ekranda da AÇIKÇA söylenir; sessizce geçilmez. */
    public function test_kirik_zincir_ekranda_bildirilir(): void
    {
        $yonetici = $this->kullanici('admin');
        $hedef = app(ActivityRecorder::class)->record('deneme', 'satir', $yonetici);

        ActivityLog::where('id', $hedef->id)->update(['ozet' => 'degistirildi']);

        $this->actingAs($yonetici)
            ->post(route('yonetim.kayitlar.dogrula'))
            ->assertSessionHas('error');
    }

    /* ── Yetki ── */

    public function test_kayit_gormek_yetki_ister(): void
    {
        $this->actingAs($this->kullanici('network'))
            ->get(route('yonetim.kayitlar'))
            ->assertForbidden();
    }

    /**
     * DOĞRULAMA, GÖRÜNTÜLEMEDEN AYRI.
     *
     * Zincirin bütünlüğünü sınamak, kaydı okumaktan farklı bir sorumluluk;
     * ayrıca doğrulama kaydın tamamını okuduğu için pahalı bir iştir.
     */
    public function test_dogrulama_ayri_yetki_ister(): void
    {
        /* Düzenek sınanıyor: varsayılanda dağıtım kapalı (bkz. TestCase). */
        $this->yetkiDagitimiAc();

        $kullanici = $this->kullanici('muhasebe');

        UserPermission::create([
            'user_id' => $kullanici->id, 'yetenek' => 'kayit.gor', 'etki' => UserPermission::IZIN,
        ]);

        $this->actingAs($kullanici)->get(route('yonetim.kayitlar'))->assertOk();
        $this->actingAs($kullanici)->post(route('yonetim.kayitlar.dogrula'))->assertForbidden();
    }

    /**
     * SİLME UCU YOK.
     *
     * Silinebilen bir denetim kaydı, denetim kaydı değildir.
     */
    public function test_silme_ucu_yoktur(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('yonetim.kayitlar.sil'));

        $this->actingAs($this->kullanici('admin'))
            ->delete(route('yonetim.kayitlar'))
            ->assertStatus(405);
    }

    /**
     * ZİNCİR APP_KEY'DEN BAĞIMSIZ.
     *
     * APP_KEY döndürülmek zorunda kalabilir (ör. sızma şüphesi). Zincir
     * anahtarı ayrı tutulduğu için anahtar değişince
     * geçmiş kayıtlar ve çapa doğrulanmaya devam eder. Anahtarı AYRI vermeden
     * değiştirmek zinciri bozar (varsayılan APP_KEY'e düşer) — bu da sınanır.
     */
    public function test_app_key_degisince_zincir_ayri_anahtarla_dogrulanmaya_devam_eder(): void
    {
        $eskiAnahtar = (string) config('app.key');
        config(['audit.zincir_anahtari' => $eskiAnahtar]);

        $kullanici = $this->kullanici('admin');
        $recorder = app(ActivityRecorder::class);

        $recorder->record('deneme', 'birinci', $kullanici);
        $recorder->record('deneme', 'ikinci', $kullanici);
        $this->assertTrue($recorder->verify()['ok']);

        // APP_KEY değişir; zincir anahtarı yerinde kalır.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->assertTrue(app(ActivityRecorder::class)->verify()['ok'], 'Zincir APP_KEY değişince bozulmamalı.');

        app(ActivityRecorder::class)->record('deneme', 'ucuncu', $kullanici);
        $this->assertTrue(app(ActivityRecorder::class)->verify()['ok']);

        // Zincir anahtarı değişirse (ya da APP_KEY'e düşerse) imzalar tutmaz.
        config(['audit.zincir_anahtari' => (string) config('app.key')]);
        $this->assertFalse(app(ActivityRecorder::class)->verify()['ok']);
    }
}
