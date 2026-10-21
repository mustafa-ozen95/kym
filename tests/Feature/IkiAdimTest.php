<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\GirisController;
use App\Models\User;
use App\Services\IkiAdim;
use App\Services\Totp;
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
class IkiAdimTest extends TestCase
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

        $this->post(route('giris'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234'])
            ->assertRedirect(route('hizli-erisim', ['pencere' => 'dogrulama']));

        $this->assertGuest();
        $this->assertNotNull(session(GirisController::BEKLEYEN), 'Kimlik oturumda beklemeli.');
    }

    #[Test]
    public function dogru_kod_oturumu_acar(): void
    {
        $kullanici = $this->kurulu();

        $this->post(route('giris'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        $this->post(route('giris.dogrulama'), ['kod' => $this->kod($kullanici)])
            ->assertRedirect(route('hizli-erisim'));

        $this->assertAuthenticatedAs($kullanici);
        $this->assertNull(session(GirisController::BEKLEYEN), 'Bekleyen kimlik temizlenmeli.');
    }

    #[Test]
    public function yanlis_kod_oturum_acmaz(): void
    {
        $this->kurulu();
        $this->post(route('giris'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        $this->post(route('giris.dogrulama'), ['kod' => '000000'])->assertSessionHasErrors('kod');

        $this->assertGuest();
    }

    /** Bekleyen kimlik yoksa doğrudan bu uca gelmek işe yaramaz. */
    #[Test]
    public function bekleyen_kimlik_olmadan_kod_ise_yaramaz(): void
    {
        $this->post(route('giris.dogrulama'), ['kod' => '123456'])
            ->assertRedirect(route('hizli-erisim', ['pencere' => 'giris']));

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
        config(['limits.iki_adim' => 3]);

        $kullanici = $this->kurulu();
        $this->post(route('giris'), ['email' => 'iki@ornek.test', 'password' => 'parola-1234']);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('giris.dogrulama'), ['kod' => '000000']);
        }

        $this->post(route('giris.dogrulama'), ['kod' => $this->kod($kullanici)]);

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

        $ikiAdim = app(IkiAdim::class);
        $ikiAdim->baslat($kullanici);
        $kodlar = $ikiAdim->onayla($kullanici->fresh(), app(Totp::class)->code((string) $kullanici->fresh()->totp_secret));

        $this->assertNotNull($kodlar);
        $kurtarma = $kodlar[0];

        $this->post(route('giris'), ['email' => 'kurtar@ornek.test', 'password' => 'parola-1234']);
        $this->post(route('giris.dogrulama'), ['kod' => $kurtarma])->assertRedirect(route('hizli-erisim'));
        $this->assertAuthenticated();

        /* Aynı kod ikinci kez geçmemeli. */
        $this->post(route('cikis'));
        $this->post(route('giris'), ['email' => 'kurtar@ornek.test', 'password' => 'parola-1234']);
        $this->post(route('giris.dogrulama'), ['kod' => $kurtarma])->assertSessionHasErrors('kod');

        $this->assertGuest();
        $this->assertCount(9, $kullanici->fresh()->totp_recovery, 'Kullanılan kod listeden düşmeli.');
    }

    /** Kurulum, kullanıcı bir kod girip çalıştığını GÖSTERENE kadar açılmaz. */
    #[Test]
    public function kurulum_onaysiz_devreye_girmez(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('hesap.iki-adim.baslat'), ['password' => 'parola-1234'])->assertRedirect();

        $kullanici->refresh();

        $this->assertNotNull($kullanici->totp_secret, 'Anahtar üretilmeli.');
        $this->assertFalse($kullanici->ikiAdimliMi(), 'Onaylanmadan devreye girmemeli.');
    }

    #[Test]
    public function kurulum_dogru_kodla_onaylanir_ve_kurtarma_kodlari_bir_kez_gosterilir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('hesap.iki-adim.baslat'), ['password' => 'parola-1234']);
        $kullanici->refresh();

        $this->actingAs($kullanici)
            ->post(route('hesap.iki-adim.onay'), ['kod' => $this->kod($kullanici), 'password' => 'parola-1234'])
            ->assertSessionHas('kurtarma_kodlari');

        $this->assertTrue($kullanici->fresh()->ikiAdimliMi());

        /* Kodlar İLK ziyarette görünür... */
        $this->actingAs($kullanici)->get(route('hesap.iki-adim'))->assertOk()->assertSee('Kurtarma kodları');

        /* ...ikincisinde yok: bir kez gösterilir, sunucuda düz metni durmaz. */
        $this->actingAs($kullanici)->get(route('hesap.iki-adim'))->assertOk()->assertDontSee('Kurtarma kodları');
    }

    /** Kapatmak parola ister: oturumu ele geçiren biri kapatamasın. */
    #[Test]
    public function kapatmak_parola_ister(): void
    {
        $kullanici = $this->kurulu();

        $this->actingAs($kullanici)->delete(route('hesap.iki-adim.kapat'), ['password' => 'yanlis'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($kullanici->fresh()->ikiAdimliMi());

        $this->actingAs($kullanici)->delete(route('hesap.iki-adim.kapat'), ['password' => 'parola-1234']);

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
            ->post(route('hesap.iki-adim.baslat'))
            ->assertSessionHasErrors('password');

        $this->actingAs($kullanici)
            ->post(route('hesap.iki-adim.baslat'), ['password' => 'yanlis-parola'])
            ->assertSessionHasErrors('password');

        $kullanici->refresh();
        $this->assertTrue($kullanici->ikiAdimliMi(), 'Onaylı ikinci adım yerinde kalmalı.');
        $this->assertSame($eskiSir, $kullanici->totp_secret);
    }

    #[Test]
    public function kurulum_onayi_parola_ister(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post(route('hesap.iki-adim.baslat'), ['password' => 'parola-1234']);
        $kullanici->refresh();

        $this->actingAs($kullanici)
            ->post(route('hesap.iki-adim.onay'), ['kod' => $this->kod($kullanici)])
            ->assertSessionHasErrors('password');

        $this->assertFalse($kullanici->fresh()->ikiAdimliMi());
    }
}
