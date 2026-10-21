<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Üyelik.
 *
 * Kayıt HERKESE AÇIK ama yeni hesap hiçbir yere erişemez. Güvenliği sağlayan
 * şey kaydın kapalı olması değil, hesabın beklemede doğmasıdır; bu testler
 * tam olarak o ayrımı kilitler.
 */
class MembershipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function kayit_olan_hesap_beklemede_dogar_ve_iceri_alinmaz(): void
    {
        $yanit = $this->post(route('kayit'), [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@ornek.test',
            'password' => 'cok-gizli-1234',
            'password_confirmation' => 'cok-gizli-1234',
        ]);

        $kullanici = User::where('email', 'yeni@ornek.test')->firstOrFail();

        $this->assertSame(Status::Beklemede, $kullanici->durum);
        /* Kayıt olan kullanıcı içeri ALINMAZ; alınsaydı onay kuralı ekranda yalan söylerdi. */
        $this->assertGuest();

        $yanit->assertRedirect(route('hizli-erisim'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'onayladıktan sonra'));
    }

    /**
     * Bekleyen hesap parolası DOĞRU olsa da giremez.
     *
     * Ve neden giremediği açıkça söylenir; yoksa kullanıcı parolasını yanlış
     * sanıp durur.
     */
    #[Test]
    public function bekleyen_hesap_giremez_ve_nedeni_soylenir(): void
    {
        User::factory()->beklemede()->create([
            'email' => 'bekleyen@ornek.test',
            'password' => Hash::make('parola-1234'),
        ]);

        $this->post(route('giris'), ['email' => 'bekleyen@ornek.test', 'password' => 'parola-1234'])
            ->assertSessionHasErrors(['email' => 'Onay bekliyor. Yönetici hesabınızı etkinleştirdiğinde girebilirsiniz.']);

        $this->assertGuest();
    }

    #[Test]
    public function askidaki_hesap_giremez(): void
    {
        User::factory()->askida()->create([
            'email' => 'askida@ornek.test',
            'password' => Hash::make('parola-1234'),
        ]);

        $this->post(route('giris'), ['email' => 'askida@ornek.test', 'password' => 'parola-1234']);

        $this->assertGuest();
    }

    #[Test]
    public function etkin_hesap_girer_ve_kayda_gecer(): void
    {
        $kullanici = User::factory()->create([
            'email' => 'etkin@ornek.test',
            'password' => Hash::make('parola-1234'),
        ]);

        $this->post(route('giris'), ['email' => 'etkin@ornek.test', 'password' => 'parola-1234'])
            ->assertRedirect(route('hizli-erisim'));

        $this->assertAuthenticatedAs($kullanici);
        $this->assertNotNull($kullanici->fresh()->son_giris_at, 'Son giriş zamanı yazılmalı.');

        $satir = ActivityLog::where('eylem', 'giris')->firstOrFail();
        $this->assertSame($kullanici->id, $satir->user_id);
        $this->assertNotNull($satir->ip, 'Nereden girildiği kayda geçmeli.');
    }

    #[Test]
    public function yanlis_parola_kabul_edilmez(): void
    {
        User::factory()->create(['email' => 'etkin@ornek.test', 'password' => Hash::make('parola-1234')]);

        $this->post(route('giris'), ['email' => 'etkin@ornek.test', 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Deneme sınırı AYARDAN gelir.
     *
     * Sabit yazılsaydı görsel test koşusu ortada takılır, testler kodla
     * ilgisiz biçimde düşerdi.
     *
     * Rotadaki "dakikada kaç istek" sayısı (`limits.giris`) ile "art arda kaç
     * yanlış parola" ayrı anahtarlardır; bu sınama kilidi sınar.
     */
    #[Test]
    public function giris_denemesi_sinirlanir(): void
    {
        config(['auth.max_attempts' => 3]);

        User::factory()->create(['email' => 'etkin@ornek.test', 'password' => Hash::make('parola-1234')]);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('giris'), ['email' => 'etkin@ornek.test', 'password' => 'yanlis']);
        }

        $this->post(route('giris'), ['email' => 'etkin@ornek.test', 'password' => 'parola-1234'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Çok fazla deneme',
            (string) session('errors')->first('email'),
        );

        /* Sınıra takılan istek DOĞRU parolayla bile geçmemeli. */
        $this->assertGuest();
    }

    /**
     * Üretim varsayılanları testle kilitli; ayara taşımak onu gevşetmek
     * değildir.
     *
     * GİRİŞ SINIRI BİLEREK SIFIR: sınır gündelik kullanımda insanı dışarıda
     * bırakabilir; gerekiyorsa .env ile açılır. Sıfır burada "sınır yok" demek ve ara katman hiç
     * eklenmiyor (bkz. App\Support\RequestLimit) — `throttle:0,1` yazmak
     * girişi herkese kapatırdı.
     *
     * İKİNCİ ADIM SINIRI KAPATILMADI: altı hanelik bir kod sınırsız denemeye
     * bırakılamaz. Bu satır onu korur.
     */
    #[Test]
    public function uretim_sinir_varsayilanlari(): void
    {
        $this->assertSame(0, config('limits.giris'));
        $this->assertSame(0, config('auth.max_attempts'));
        $this->assertSame(5, config('limits.iki_adim'));
        $this->assertSame(5, config('limits.kayit'));
        $this->assertSame(8, config('limits.parola_uzunluk'));
    }

    #[Test]
    public function cikis_oturumu_kapatir_ve_kayda_gecer(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('cikis'))->assertRedirect(route('hizli-erisim'));

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['eylem' => 'cikis', 'user_id' => $kullanici->id]);
    }

    /**
     * Denetim kaydı ZİNCİRLİ.
     *
     * Aradan bir satır değiştirmek zincirin geri kalanını bozmalı; yoksa
     * "kim girdi" kaydı sonradan düzenlenebilir bir metinden ibaret olurdu.
     */
    #[Test]
    public function kayit_zinciri_degisiklikte_bozulur(): void
    {
        $kullanici = User::factory()->create();
        $recorder = app(ActivityRecorder::class);

        $recorder->record('giris', 'birinci', $kullanici);
        $ortadaki = $recorder->record('giris', 'ikinci', $kullanici);
        $recorder->record('giris', 'ucuncu', $kullanici);

        $this->assertTrue($recorder->verify()['ok'], 'Dokunulmamış zincir bütün olmalı.');

        $ortadaki->forceFill(['ozet' => 'sonradan degistirildi'])->save();

        $sonuc = $recorder->verify();

        $this->assertFalse($sonuc['ok']);
        $this->assertSame($ortadaki->id, $sonuc['broken_id']);
    }

    /**
     * ASKIYA ALMAK AÇIK OTURUMU DA KAPATIR.
     *
     * Durum yalnızca girişte sorulsaydı askıya alınan kişi sekmesi açık
     * olduğu sürece tam yetkiyle çalışmaya devam ederdi. Burası kimliğin
     * kaynağı — bağlı projeler buraya bakıyor.
     */
    #[Test]
    public function askiya_alinan_hesabin_oturumu_kapanir(): void
    {
        $kullanici = User::factory()->create(['durum' => Status::Etkin]);

        $this->actingAs($kullanici)->get(route('hesap.profil'))->assertOk();

        $kullanici->forceFill(['durum' => Status::Askida])->save();

        $this->actingAs($kullanici)
            ->get(route('hesap.profil'))
            ->assertRedirect(route('hizli-erisim', ['pencere' => 'giris']));

        $this->assertGuest();
    }
}
