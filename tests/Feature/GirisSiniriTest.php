<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\IstekSiniri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Giriş ucundaki istek sınırı — sıfır "sınır yok" demektir.
 *
 * SIFIRIN TUZAĞI: sınır rotaya doğrudan yazılıyordu
 * (`'throttle:'.config('limits.giris').',1'`). Orada sıfır, sınırı kaldırmak
 * yerine "dakikada sıfır istek" anlamına geliyordu: `LIMIT_GIRIS=0` verip
 * sınırı kapattığını sanan kişi giriş ucunu HERKESE kapatırdı ve sebebi
 * ayarın kendisinde görünmezdi. Karar artık IstekSiniri'nde ve sıfırda ara
 * katman hiç eklenmiyor.
 *
 * Giriş sınırı varsayılan olarak kapalıdır. İkinci adım sınırı
 * (limits.iki_adim) AYRI ve açık kalır.
 */
class GirisSiniriTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kararın kendisi. Rotaya bakmak yanıltıcı olurdu: rotalar AÇILIŞTA
     * kuruluyor ve testte config'i değiştirmek onları yeniden kurmuyor —
     * öyle bir sınama, ölçtüğünü sandığı şeyi ölçmez.
     */
    #[Test]
    public function sifir_sinirda_ara_katman_uretilmez(): void
    {
        config(['limits.giris' => 0]);

        $this->assertSame([], IstekSiniri::rotaIcin('giris'));
    }

    /** Eksi bir sınırın anlamı yok; o da "sınır yok" sayılır. */
    #[Test]
    public function eksi_sinir_da_sinirsiz_sayilir(): void
    {
        config(['limits.giris' => -3]);

        $this->assertSame([], IstekSiniri::rotaIcin('giris'));
    }

    #[Test]
    public function sifir_sinirla_yanlis_parola_art_arda_denenebilir(): void
    {
        config(['limits.giris' => 0]);

        User::factory()->create([
            'email' => 'kisi@ornek.test',
            'password' => Hash::make('dogru-parola-1234'),
        ]);

        for ($i = 0; $i < 15; $i++) {
            $yanit = $this->post(route('giris'), [
                'email' => 'kisi@ornek.test',
                'password' => 'yanlis',
            ]);

            /* 429 GELMEMELİ: sınır yokken uç açık kalır. */
            $this->assertNotSame(429, $yanit->getStatusCode());
        }
    }

    #[Test]
    public function sinir_verilince_ara_katman_eklenir(): void
    {
        config(['limits.giris' => 7]);

        $sinirlar = IstekSiniri::rotaIcin('giris');

        $this->assertSame(['throttle:7,1'], $sinirlar);
    }

    /**
     * ASIL TUZAK BUYDU.
     *
     * Kilit ile istek sınırı aynı ayarı (`limits.giris`) okuyordu. Sıfır
     * verildiğinde rotadaki sınır kalkıyor ama kilit KALKMIYOR:
     * `tooManyAttempts($k, 0)` sayacı sıfıra eşit olduğu anda dolu sayar, yani
     * İLK yanlış paroladan sonra kilitlerdi. Sınırı kapatmak isteyen kişi onu
     * en sert hâline getirirdi ve sebebi ayarda görünmezdi.
     */
    #[Test]
    public function kilit_kapaliyken_ilk_yanlis_parola_kilitlemez(): void
    {
        config(['auth.max_attempts' => 0]);

        User::factory()->create([
            'email' => 'kisi2@ornek.test',
            'password' => Hash::make('dogru-parola-1234'),
        ]);

        $this->post(route('giris'), ['email' => 'kisi2@ornek.test', 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');

        $hata = (string) session('errors')?->first('email');
        $this->assertStringNotContainsString('Çok fazla deneme', $hata);

        /* Doğru parola hâlâ geçer. */
        $this->post(route('giris'), ['email' => 'kisi2@ornek.test', 'password' => 'dogru-parola-1234']);
        $this->assertAuthenticated();
    }
}
