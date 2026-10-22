<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use App\Services\Totp;
use App\Services\TwoStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * İkinci adımın giriş akışı.
 *
 * Buradaki asıl mesele şu: parolanın doğru olması OTURUM AÇMAYA YETMEZ.
 * Kullanıcı kod doğrulanana kadar oturumdan düşürülür; aksi hâlde parolayı
 * ele geçiren biri kod sorulan ekranı görmezden gelip doğrudan bir adrese
 * giderek içeri girebilirdi.
 */
class TwoStepTest extends TestCase
{
    use RefreshDatabase;

    private function kurulu(): User
    {
        return User::factory()->ikiAdimli()->create([
            'email' => 'iki@ornek.test',
            'password' => Hash::make('parola-1234'),
        ]);
    }

    private function kod(User $kullanici): string
    {
        return app(Totp::class)->code((string) $kullanici->totp_secret);
    }

    #[Test]
    public function dogru_parola_tek_basina_oturum_acmaz(): void
    {
        $this->kurulu();

        $this->post(route('login'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234'])
            ->assertRedirect(route('quick-access', ['window' => 'verify']));

        $this->assertGuest();
        $this->assertNotNull(session(LoginController::PENDING), 'Kimlik oturumda beklemeli.');
    }

    #[Test]
    public function dogru_kod_oturumu_acar(): void
    {
        $kullanici = $this->kurulu();

        $this->post(route('login'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        $this->post(route('login.verify'), ['code' => $this->kod($kullanici)])
            ->assertRedirect(route('quick-access'));

        $this->assertAuthenticatedAs($kullanici);
        $this->assertNull(session(LoginController::PENDING), 'Bekleyen kimlik temizlenmeli.');
    }

    #[Test]
    public function yanlis_kod_oturum_acmaz(): void
    {
        $this->kurulu();
        $this->post(route('login'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        $this->post(route('login.verify'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /** Bekleyen kimlik yoksa doğrudan bu uca gelmek işe yaramaz. */
    #[Test]
    public function bekleyen_kimlik_olmadan_kod_ise_yaramaz(): void
    {
        $this->post(route('login.verify'), ['code' => '123456'])
            ->assertRedirect(route('quick-access', ['window' => 'login']));

        $this->assertGuest();
    }

    /**
     * Kod denemesi SINIRLI.
     *
     * Altı hane, sınırsız denemede dakikalar içinde bulunur.
     */
    #[Test]
    public function kod_denemesi_sinirlanir(): void
    {
        config(['limits.two_step' => 3]);

        $kullanici = $this->kurulu();
        $this->post(route('login'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.verify'), ['code' => '000000']);
        }

        $this->post(route('login.verify'), ['code' => $this->kod($kullanici)]);

        /* Sınıra takılan istek DOĞRU kodla bile geçmemeli. */
        $this->assertGuest();
    }

    /** Kurtarma kodu çalışır ve BİR KEZ kullanılır. */
    #[Test]
    public function kurtarma_kodu_bir_kez_calisir(): void
    {
        $kullanici = User::factory()->create([
            'email' => 'kurtar@ornek.test',
            'password' => Hash::make('parola-1234'),
        ]);

        $ikiAdim = app(TwoStep::class);
        $ikiAdim->baslat($kullanici);
        $kodlar = $ikiAdim->onayla($kullanici->fresh(), app(Totp::class)->code((string) $kullanici->fresh()->totp_secret));

        $this->assertNotNull($kodlar);
        $kurtarma = $kodlar[0];

        $this->post(route('login'), ['email' => 'kurtar@ornek.test', 'password' => 'parola-1234']);
        $this->post(route('login.verify'), ['code' => $kurtarma])->assertRedirect(route('quick-access'));
        $this->assertAuthenticated();

        /* Aynı kod ikinci kez geçmemeli. */
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'kurtar@ornek.test', 'password' => 'parola-1234']);
        $this->post(route('login.verify'), ['code' => $kurtarma])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertCount(9, $kullanici->fresh()->totp_recovery, 'Kullanılan kod listeden düşmeli.');
    }

    /** Kurulum, kullanıcı bir kod girip çalıştığını GÖSTERENE kadar açılmaz. */
    #[Test]
    public function kurulum_onaysiz_devreye_girmez(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('account.two-step.start'), ['password' => 'parola-1234'])->assertRedirect();

        $kullanici->refresh();

        $this->assertNotNull($kullanici->totp_secret, 'Anahtar üretilmeli.');
        $this->assertFalse($kullanici->ikiAdimliMi(), 'Onaylanmadan devreye girmemeli.');
    }

    #[Test]
    public function kurulum_dogru_kodla_onaylanir_ve_kurtarma_kodlari_bir_kez_gosterilir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('account.two-step.start'), ['password' => 'parola-1234']);
        $kullanici->refresh();

        $this->actingAs($kullanici)
            ->post(route('account.two-step.confirm'), ['code' => $this->kod($kullanici), 'password' => 'parola-1234'])
            ->assertSessionHas('kurtarma_kodlari');

        $this->assertTrue($kullanici->fresh()->ikiAdimliMi());

        /* Kodlar İLK ziyarette görünür... */
        $this->actingAs($kullanici)->get(route('account.two-step'))->assertOk()->assertSee('Kurtarma kodları');

        /* ...ikincisinde yok: bir kez gösterilir, sunucuda düz metni durmaz. */
        $this->actingAs($kullanici)->get(route('account.two-step'))->assertOk()->assertDontSee('Kurtarma kodları');
    }

    /** Kapatmak parola ister: oturumu ele geçiren biri kapatamasın. */
    #[Test]
    public function kapatmak_parola_ister(): void
    {
        $kullanici = $this->kurulu();

        $this->actingAs($kullanici)->delete(route('account.two-step.disable'), ['password' => 'yanlis'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($kullanici->fresh()->ikiAdimliMi());

        $this->actingAs($kullanici)->delete(route('account.two-step.disable'), ['password' => 'parola-1234']);

        $this->assertFalse($kullanici->fresh()->ikiAdimliMi());
    }

    /** Gizli anahtar ve kurtarma kodları hiçbir yanıtta görünmez. */
    #[Test]
    public function gizli_anahtar_disari_sizmaz(): void
    {
        $kullanici = $this->kurulu();

        $this->assertArrayNotHasKey('totp_secret', $kullanici->toArray());
        $this->assertArrayNotHasKey('totp_recovery', $kullanici->toArray());

        /* Veritabanında da düz metin durmaz. */
        $ham = \DB::table('users')->where('id', $kullanici->id)->value('totp_secret');
        $this->assertNotSame((string) $kullanici->totp_secret, $ham, 'Anahtar şifreli saklanmalı.');
    }

    /**
     * PAROLASIZ YENİDEN KURULUM İKİNCİ ADIMI DÜŞÜREMEZ.
     *
     * baslat() onaylı ikinci adımı ve kurtarma kodlarını siler; oturumu
     * parolasız ele geçiren biri bu yoldan korumayı kaldırıp kendi
     * cihazını bağlayabilirdi. Kapatma zaten parola istiyordu.
     */
    #[Test]
    public function kurulu_ikinci_adim_parolasiz_yeniden_baslatilamaz(): void
    {
        $kullanici = User::factory()->ikiAdimli()->create();
        $eskiSir = $kullanici->totp_secret;

        $this->actingAs($kullanici)
            ->post(route('account.two-step.start'))
            ->assertSessionHasErrors('password');

        $this->actingAs($kullanici)
            ->post(route('account.two-step.start'), ['password' => 'yanlis-parola'])
            ->assertSessionHasErrors('password');

        $kullanici->refresh();
        $this->assertTrue($kullanici->ikiAdimliMi(), 'Onaylı ikinci adım yerinde kalmalı.');
        $this->assertSame($eskiSir, $kullanici->totp_secret);
    }

    #[Test]
    public function kurulum_onayi_parola_ister(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('account.two-step.start'), ['password' => 'parola-1234']);
        $kullanici->refresh();

        $this->actingAs($kullanici)
            ->post(route('account.two-step.confirm'), ['code' => $this->kod($kullanici)])
            ->assertSessionHasErrors('password');

        $this->assertFalse($kullanici->fresh()->ikiAdimliMi());
    }
}
