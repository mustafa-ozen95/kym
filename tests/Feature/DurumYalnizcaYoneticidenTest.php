<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BİR HESABI YALNIZCA YÖNETİCİ ETKİNLEŞTİREBİLİR.
 *
 * Kural: kullanıcı, yönetici etkinleştirmedikçe hiçbir yoldan etkin duruma
 * geçemez.
 *
 * Kural ekranlarda zaten vardı (kayıt "beklemede" açıyor, durum değiştirmek
 * `kullanici.onayla` istiyor) ama alan TOPLU ATAMAYA açıktı: kullanıcıdan
 * veri alan herhangi bir güncelleme ucu — bugün olmayan, yarın eklenecek bir
 * tanesi — gövdesine "durum=etkin" yazan birine açılırdı. Doğrulama
 * kurallarına güvenmek yetmez; unutulan tek bir kural sessizce yetki verir.
 */
final class DurumYalnizcaYoneticidenTest extends TestCase
{
    use RefreshDatabase;

    public function test_kayit_olan_beklemede_dogar(): void
    {
        $this->post(route('kayit'), [
            'name' => 'Deneme Kişi',
            'email' => 'deneme@ornek.test',
            'password' => 'parola-12345',
            'password_confirmation' => 'parola-12345',
        ]);

        $this->assertSame(Durum::Beklemede, User::whereEmail('deneme@ornek.test')->firstOrFail()->durum);
    }

    /** Gövdeye elle yazılan durum KAYDA ULAŞMAZ. */
    public function test_kayit_govdesine_yazilan_durum_islemez(): void
    {
        $this->post(route('kayit'), [
            'name' => 'Kurnaz Kişi',
            'email' => 'kurnaz@ornek.test',
            'password' => 'parola-12345',
            'password_confirmation' => 'parola-12345',
            'durum' => 'etkin',
        ]);

        $this->assertSame(Durum::Beklemede, User::whereEmail('kurnaz@ornek.test')->firstOrFail()->durum);
    }

    /** Kendi profilini güncelleyen kendini etkinleştiremez. */
    public function test_kullanici_kendi_profilinden_etkinlesemez(): void
    {
        $kullanici = User::factory()->create(['durum' => Durum::Beklemede]);

        $this->actingAs($kullanici)->put(route('hesap.profil.kaydet'), [
            'name' => 'Yeni Ad',
            'email' => $kullanici->email,
            'durum' => 'etkin',
        ]);

        $this->assertSame(Durum::Beklemede, $kullanici->fresh()->durum);
    }

    /** Toplu atama alanı kapalı: update() ile durum yazılamaz. */
    public function test_toplu_atama_durumu_yazmaz(): void
    {
        $kullanici = User::factory()->create(['durum' => Durum::Beklemede]);

        $kullanici->update(['durum' => Durum::Etkin, 'name' => 'Ad Değişti']);

        $taze = $kullanici->fresh();
        $this->assertSame(Durum::Beklemede, $taze->durum, 'Durum toplu atamayla yazılmamalı.');
        $this->assertSame('Ad Değişti', $taze->name, 'Öteki alanlar yazılmalı.');
    }
}
