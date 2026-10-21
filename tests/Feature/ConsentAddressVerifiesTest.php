<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hesap etkinleşince adres doğrulanmış sayılır — HANGİ YOLDAN OLURSA OLSUN.
 *
 * Doğrulama zorunlu değilken adrese kefil olan kişi, hesabı etkinleştiren
 * yöneticidir. Bu yazma yalnızca kullanıcı yönetimi ekranında durursa hesabı
 * başka bir yoldan etkinleştiren (konsol, toplu işlem) adımı atlar.
 *
 * SESSİZ ARIZA: merkez adresi "doğrulanmamış" diye bildirirse, kimliği
 * yalnızca doğrulanmış adreste eşleyen projelerde kullanıcı "eşleşen hesap
 * yok" duvarına çarpar; merkezde her şey yolunda görünür.
 */
final class ConsentAddressVerifiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_beklemedeki_hesap_etkinlesince_adres_dogrulanir(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Status::Beklemede,
            'email_verified_at' => null,
        ]);

        $this->assertNull($kullanici->email_verified_at);

        /* Ekrandan değil, doğrudan modelden: kural her yolda geçerli olmalı. */
        $kullanici->durum = Status::Etkin;
        $kullanici->save();

        $this->assertNotNull($kullanici->fresh()->email_verified_at, 'Etkinleşen hesabın adresi doğrulanmış sayılmalı.');
    }

    /** Açıkça null yazmak bir karardır; kural onu ezmez. */
    public function test_acikca_yazilan_deger_ezilmez(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Status::Etkin,
            'email_verified_at' => null,
        ]);

        $this->assertNull($kullanici->fresh()->email_verified_at);
    }

    /** Askıya alınan hesapta kural çalışmaz. */
    public function test_askiya_alinan_hesapta_calismaz(): void
    {
        $kullanici = User::factory()->create([
            'durum' => Status::Beklemede,
            'email_verified_at' => null,
        ]);

        $kullanici->durum = Status::Askida;
        $kullanici->save();

        $this->assertNull($kullanici->fresh()->email_verified_at);
    }
}
