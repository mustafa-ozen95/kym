<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Services\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * E-posta doğrulama — İSTEĞE BAĞLI bir katman.
 *
 * ŞART OLAN YÖNETİCİ ONAYIDIR. Varsayılan kurulumda kimseye doğrulama postası
 * gitmez; hesabı bir insan inceleyip etkinleştirir ve onayı veren kişi adresin
 * o kişiye ait olduğuna kefil olur. Ayar açıldığında bu kefalet yerini
 * kullanıcının tıkladığı bağlantıya bırakır.
 *
 * Testlerin kilitlediği asıl şey, ayarın gerçekten BİR ŞEY DEĞİŞTİRDİĞİ:
 * kapalıyken onay doğrulama yerine geçmeli, açıkken geçmemeli. İkisinden biri
 * kaçarsa ayar ya işe yaramaz olur ya da herkesi kilitler.
 */
final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function zorunluYap(): void
    {
        Setting::yaz(EmailVerification::AYAR, true);
    }

    private function kayit(array $ustune = []): array
    {
        return [
            'name' => 'Ali Veli',
            'email' => 'ali@ornek.test',
            'password' => 'cok-gizli-1616',
            'password_confirmation' => 'cok-gizli-1616',
            ...$ustune,
        ];
    }

    /* ── Varsayılan: doğrulama şart değil ── */

    public function test_varsayilan_olarak_dogrulama_zorunlu_degildir(): void
    {
        $this->assertFalse(app(EmailVerification::class)->zorunluMu());
    }

    public function test_kapaliyken_kayitta_posta_gitmez(): void
    {
        Notification::fake();

        $this->post(route('kayit'), $this->kayit())->assertRedirect();

        Notification::assertNothingSent();
    }

    /**
     * Kapalıyken YÖNETİCİ ONAYI doğrulama yerine geçer.
     *
     * Projeler ilk eşleşmede "doğrulanmış e-posta" arıyor; işaret hiç
     * konmasaydı hiçbir hesap hiçbir projeye bağlanamazdı.
     */
    public function test_kapaliyken_yonetici_onayi_adresi_dogrulanmis_sayar(): void
    {
        $bekleyen = User::factory()->create(['durum' => Status::Beklemede, 'email_verified_at' => null]);

        $this->actingAs(User::factory()->yonetici()->create(['durum' => Status::Etkin]))
            ->put(route('yonetim.kullanicilar.guncelle', $bekleyen), [
                'durum' => Status::Etkin->value,
                'rol' => Role::firstWhere('slug', 'muhasebe')->uuid,
            ]);

        $this->assertNotNull($bekleyen->fresh()->email_verified_at);
    }

    public function test_kapaliyken_dogrulanmamis_hesap_giris_yapabilir(): void
    {
        User::factory()->create([
            'email' => 'ali@ornek.test',
            'password' => 'cok-gizli-1616',
            'durum' => Status::Etkin,
            'email_verified_at' => null,
        ]);

        $this->post(route('giris'), ['email' => 'ali@ornek.test', 'password' => 'cok-gizli-1616']);

        $this->assertAuthenticated();
    }

    /* ── Açıkken ── */

    public function test_acikken_kayitta_dogrulama_postasi_gider(): void
    {
        Notification::fake();
        $this->zorunluYap();

        $this->post(route('kayit'), $this->kayit())->assertRedirect();

        Notification::assertSentTo(User::firstWhere('email', 'ali@ornek.test'), EmailVerificationNotification::class);
    }

    /**
     * Açıkken onay TEK BAŞINA yetmez.
     *
     * Yetseydi ayar hiçbir şey değiştirmezdi.
     */
    public function test_acikken_yonetici_onayi_adresi_dogrulamis_saymaz(): void
    {
        $this->zorunluYap();

        $bekleyen = User::factory()->create(['durum' => Status::Beklemede, 'email_verified_at' => null]);

        $this->actingAs(User::factory()->yonetici()->create(['durum' => Status::Etkin]))
            ->put(route('yonetim.kullanicilar.guncelle', $bekleyen), [
                'durum' => Status::Etkin->value,
                'rol' => Role::firstWhere('slug', 'muhasebe')->uuid,
            ]);

        $this->assertNull($bekleyen->fresh()->email_verified_at);
    }

    public function test_acikken_dogrulanmamis_hesap_giris_yapamaz(): void
    {
        $this->zorunluYap();

        User::factory()->create([
            'email' => 'ali@ornek.test',
            'password' => 'cok-gizli-1616',
            'durum' => Status::Etkin,
            'email_verified_at' => null,
        ]);

        $this->post(route('giris'), ['email' => 'ali@ornek.test', 'password' => 'cok-gizli-1616'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /* ── Bağlantı ── */

    /**
     * Bağlantı OTURUMSUZ açılır.
     *
     * Oturum şartı konsaydı kullanıcı, doğrulamak için giriş yapmak, giriş
     * yapmak için doğrulamak zorunda kalırdı.
     */
    public function test_imzali_baglanti_oturumsuz_calisir(): void
    {
        $this->zorunluYap();

        $kullanici = User::factory()->create(['durum' => Status::Etkin, 'email_verified_at' => null]);

        $this->get($this->baglanti($kullanici))->assertRedirect();

        $this->assertNotNull($kullanici->fresh()->email_verified_at);
        /* Doğrulama oturum AÇMAZ: hesap hâlâ yönetici onayı bekliyor olabilir. */
        $this->assertGuest();
    }

    /** İmza olmadan uç açılmaz; adres kurcalanamaz. */
    public function test_imzasiz_baglanti_reddedilir(): void
    {
        $kullanici = User::factory()->create(['email_verified_at' => null]);

        $this->get('/eposta/dogrula/'.$kullanici->getRouteKey().'/'.sha1($kullanici->email))
            ->assertForbidden();

        $this->assertNull($kullanici->fresh()->email_verified_at);
    }

    /**
     * Adres değişince eski bağlantı ölür.
     *
     * `hash` alanı adresin özetini taşır: kullanıcı e-postasını
     * değiştirdiğinde eskisine giden bağlantı yeni adresi doğrulamamalı.
     */
    public function test_adres_degisince_eski_baglanti_gecersizdir(): void
    {
        $kullanici = User::factory()->create(['email' => 'eski@ornek.test', 'email_verified_at' => null]);

        $adres = $this->baglanti($kullanici);

        $kullanici->forceFill(['email' => 'yeni@ornek.test'])->save();

        $this->get($adres)->assertRedirect();

        $this->assertNull($kullanici->fresh()->email_verified_at);
    }

    /**
     * Yeniden gönderme ucu, hesabın VARLIĞINI ele vermez.
     *
     * Farklı yanıt verilseydi bu uç, kimin kayıtlı olduğunu sorgulamanın
     * yoluna dönerdi.
     */
    public function test_yeniden_gonderme_hesabin_varligini_ele_vermez(): void
    {
        Notification::fake();
        $this->zorunluYap();

        $var = $this->post(route('dogrulama.gonder'), ['email' => 'yok@ornek.test']);

        User::factory()->create(['email' => 'ali@ornek.test', 'email_verified_at' => null]);
        $yok = $this->post(route('dogrulama.gonder'), ['email' => 'ali@ornek.test']);

        $this->assertSame($var->getStatusCode(), $yok->getStatusCode());
        $this->assertSame(session()->get('status'), 'Adres kayıtlıysa doğrulama bağlantısı gönderildi.');
    }

    /**
     * Adres SIRALI ANAHTAR TAŞIMAZ.
     *
     * Laravel'in hazır doğrulama bildirimi `getKey()` kullanıyor ve bu, kaç
     * kullanıcı olduğunu ve hesabın kaçıncı sırada açıldığını söyleyen bir
     * sayıyı posta kutusuna ve adres çubuğuna yazardı.
     */
    public function test_baglanti_uuid_tasir_sirali_anahtar_degil(): void
    {
        Notification::fake();
        $this->zorunluYap();

        $kullanici = User::factory()->create(['email_verified_at' => null]);
        $kullanici->notify(new EmailVerificationNotification);

        Notification::assertSentTo($kullanici, EmailVerificationNotification::class,
            function (EmailVerificationNotification $bildirim) use ($kullanici): bool {
                $adres = $bildirim->toMail($kullanici)->actionUrl;

                $this->assertStringContainsString($kullanici->uuid, $adres);
                $this->assertStringNotContainsString('/dogrula/'.$kullanici->getKey().'/', $adres);

                return true;
            });
    }

    private function baglanti(User $kullanici): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $kullanici->getRouteKey(),
            'hash' => sha1($kullanici->getEmailForVerification()),
        ]);
    }
}
