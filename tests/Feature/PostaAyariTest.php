<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Mail\DenemeIletisi;
use App\Models\Ayar;
use App\Models\User;
use App\Services\EpostaDogrulama;
use App\Services\Posta\PostaAyarlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Posta ayarları ve e-posta doğrulama zorunluluğu.
 *
 * Bu ekranın asıl riski, kendini kilitlemek: posta gitmiyorken doğrulamayı
 * zorunlu tutan bir kurulum, yeni kayıtların tamamını sessizce kilitler ve
 * bunu fark edecek kimse olmaz. Testlerin çoğu o kapıyı bekliyor.
 */
final class PostaAyariTest extends TestCase
{
    use RefreshDatabase;

    private function yonetici(): User
    {
        return User::factory()->yonetici()->create(['durum' => Durum::Etkin]);
    }

    /** @param  array<string, mixed>  $ustune */
    private function form(array $ustune = []): array
    {
        return [
            'sunucu' => 'posta.ornek.test',
            'port' => 587,
            'sifreleme' => 'tls',
            'kullanici' => 'kimlik@ornek.test',
            'parola' => 'gizli-parola',
            'gonderen' => 'kimlik@ornek.test',
            'gonderen_ad' => 'Kimlik Yönetim Merkezi',
            ...$ustune,
        ];
    }

    public function test_yonetici_posta_ayarlarini_kaydeder(): void
    {
        $this->actingAs($this->yonetici())
            ->put(route('yonetim.ayarlar.posta'), $this->form())
            ->assertRedirect();

        $this->assertSame('posta.ornek.test', Ayar::hepsi()[PostaAyarlari::SUNUCU]);
        $this->assertSame('587', Ayar::hepsi()[PostaAyarlari::PORT]);
    }

    /**
     * Parola VERİTABANINDA AÇIK DURMAZ.
     *
     * Veritabanı yedeğine erişen biri posta hesabını da ele geçirmemeli.
     */
    public function test_parola_sifreli_saklanir_ve_ekrana_basilmaz(): void
    {
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());

        $saklanan = (string) Ayar::hepsi()[PostaAyarlari::PAROLA];

        $this->assertNotSame('gizli-parola', $saklanan);
        $this->assertSame('gizli-parola', Crypt::decryptString($saklanan));

        $this->actingAs($yonetici)->get(route('yonetim.ayarlar'))
            ->assertOk()
            ->assertDontSee('gizli-parola');
    }

    /**
     * Boş parola "sil" DEĞİL "değiştirme" demektir.
     *
     * Ekranda hiç görünmediği için, başka bir alanı düzeltmek isteyen biri her
     * kaydedişte parolayı sıfırlardı.
     */
    public function test_bos_parola_eskisini_korur(): void
    {
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());
        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form(['parola' => '', 'gonderen_ad' => 'Başka Ad']));

        $this->assertSame('gizli-parola', Crypt::decryptString((string) Ayar::hepsi()[PostaAyarlari::PAROLA]));
        $this->assertSame('Başka Ad', Ayar::hepsi()[PostaAyarlari::GONDEREN_AD]);
    }

    /**
     * Parola GİTTİĞİ YERE bağlıdır: sunucu, port ya da kullanıcı değişirse ve
     * yeni parola verilmezse eskisi silinir; yoksa ayar yetkisi olan biri
     * kayıtlı parolayı kendi seçtiği adrese gönderebilirdi.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function hedefDegisiklikleri(): array
    {
        return [
            'sunucu' => [['sunucu' => 'saldirgan.test']],
            'port' => [['port' => 2525]],
            'kullanici' => [['kullanici' => 'baska@ornek.test']],
        ];
    }

    /** @param  array<string, mixed>  $degisiklik */
    #[\PHPUnit\Framework\Attributes\DataProvider('hedefDegisiklikleri')]
    public function test_hedef_degisince_parola_verilmediyse_eskisi_silinir(array $degisiklik): void
    {
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());
        $this->assertTrue(filled(Ayar::hepsi()[PostaAyarlari::PAROLA] ?? null));

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form(['parola' => ''] + $degisiklik));

        $this->assertFalse(app(PostaAyarlari::class)->goster()['parola_var']);
    }

    public function test_hedef_degisirken_yeni_parola_verilirse_o_saklanir(): void
    {
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());
        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form(['sunucu' => 'yeni.ornek.test', 'parola' => 'yeni-parola']));

        $this->assertSame('yeni-parola', Crypt::decryptString((string) Ayar::hepsi()[PostaAyarlari::PAROLA]));
    }

    /** Ayar değişince eski denemenin sonucu bir şey kanıtlamaz. */
    public function test_ayar_degisince_deneme_damgasi_silinir(): void
    {
        Ayar::yaz(PostaAyarlari::SINANDI, '2026-01-01 10:00:00');

        $this->actingAs($this->yonetici())->put(route('yonetim.ayarlar.posta'), $this->form());

        $this->assertFalse(app(PostaAyarlari::class)->sinandiMi());
    }

    public function test_deneme_iletisi_istegi_yapanin_adresine_gider(): void
    {
        Mail::fake();

        $yonetici = $this->yonetici();
        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());

        $this->actingAs($yonetici)->post(route('yonetim.ayarlar.posta.deneme'))->assertRedirect();

        Mail::assertSent(DenemeIletisi::class, fn (DenemeIletisi $ileti): bool => $ileti->hasTo($yonetici->email));
        $this->assertTrue(app(PostaAyarlari::class)->sinandiMi());
    }

    /**
     * POSTA GİTMİYORKEN ZORUNLULUK AÇILMAZ.
     *
     * Bu, ekranın en önemli kapısı: açılsaydı doğrulama bağlantısı kimseye
     * ulaşmaz ve yeni kayıtların tamamı sessizce kilitlenirdi.
     */
    public function test_deneme_yapilmadan_dogrulama_zorunlu_tutulamaz(): void
    {
        $this->actingAs($this->yonetici())
            ->put(route('yonetim.ayarlar.kaydet'), ['eposta_dogrulama_zorunlu' => '1'])
            ->assertSessionHas('error');

        $this->assertFalse(Ayar::bool(EpostaDogrulama::AYAR));
    }

    public function test_basarili_denemeden_sonra_zorunluluk_acilir(): void
    {
        Mail::fake();

        $yonetici = $this->yonetici();
        $this->actingAs($yonetici)->put(route('yonetim.ayarlar.posta'), $this->form());
        $this->actingAs($yonetici)->post(route('yonetim.ayarlar.posta.deneme'));

        $this->actingAs($yonetici)
            ->put(route('yonetim.ayarlar.kaydet'), ['eposta_dogrulama_zorunlu' => '1'])
            ->assertSessionHas('status');

        $this->assertTrue(Ayar::bool(EpostaDogrulama::AYAR));
    }

    /**
     * Kutu, zorunluluk AÇIKKEN kilitlenmez.
     *
     * Kilitlenseydi ayar tek yönlü olurdu: posta sunucusu bozulduğunda
     * yönetici zorunluluğu kapatamaz, kimse içeri giremezdi.
     */
    public function test_acikken_kapatmak_her_zaman_mumkundur(): void
    {
        Ayar::yaz(EpostaDogrulama::AYAR, true);

        $this->actingAs($this->yonetici())
            ->put(route('yonetim.ayarlar.kaydet'), [])
            ->assertSessionHas('status');

        $this->assertFalse(Ayar::bool(EpostaDogrulama::AYAR));
    }

    public function test_sıradan_kullanici_posta_ayarina_dokunamaz(): void
    {
        $this->actingAs(User::factory()->create(['durum' => Durum::Etkin]))
            ->put(route('yonetim.ayarlar.posta'), $this->form())
            ->assertForbidden();
    }
}
