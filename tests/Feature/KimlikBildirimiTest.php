<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Durum;
use App\Jobs\KimlikDegisikliginiBildirJob;
use App\Models\OauthIstemci;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Merkezdeki değişiklik projelere ANINDA gider ve erişim ORADA da biter.
 *
 * Yalnız aralıklı yoklamaya dayanılsaydı erişimi kaldırılan biri bir sonraki
 * yoklamaya kadar projede çalışmaya devam edebilirdi.
 *
 * Bildirim VERİ TAŞIMAZ: yalnızca hangi kimliğin değiştiğini söyler. Yeni rol
 * ya da durum gönderilmez; proje doğruyu merkezden kendi sorar. Bu yüzden
 * bildirim yolu ele geçse bile kimsenin rolü yükseltilemez.
 */
final class KimlikBildirimiTest extends TestCase
{
    use RefreshDatabase;

    private function istemci(string $kok = 'https://proje.ornek.test'): OauthIstemci
    {
        $istemci = new OauthIstemci;

        $istemci->forceFill([
            'name' => 'Proje',
            'secret' => bcrypt('sir'),
            'bildirim_sirri' => 'bildirim-sirri',
            'provider' => 'users',
            'redirect_uris' => [$kok.'/giris/merkez/geri'],
            'grant_types' => ['authorization_code'],
            'revoked' => false,
        ])->save();

        return $istemci;
    }

    #[Test]
    public function durum_degisince_bildirim_kuyruga_alinir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->create(['durum' => Durum::Etkin]);
        $kullanici->durumuAyarla(Durum::Askida);

        Queue::assertPushed(
            KimlikDegisikliginiBildirJob::class,
            fn (KimlikDegisikliginiBildirJob $is): bool => $is->subject === (string) $kullanici->uuid,
        );
    }

    #[Test]
    public function rol_degisince_de_bildirim_kuyruga_alinir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->rol('teknik')->create();
        $kullanici->update(['rol_id' => Rol::where('slug', 'muhasebe')->value('id')]);

        Queue::assertPushed(KimlikDegisikliginiBildirJob::class);
    }

    /** Ad değişikliği projeleri ilgilendirmiyor; her kaydetmede bildirim atılmaz. */
    #[Test]
    public function alakasiz_degisiklik_bildirim_uretmez(): void
    {
        Queue::fake();

        $kullanici = User::factory()->create();
        $kullanici->update(['name' => 'Yeni Ad']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function bildirim_projenin_koku_ile_imzali_gider(): void
    {
        Http::fake();

        $istemci = $this->istemci();
        $subject = (string) Str::uuid();

        (new KimlikDegisikliginiBildirJob($subject))->handle();

        Http::assertSent(function ($istek) use ($subject): bool {
            return $istek->url() === 'https://proje.ornek.test/api/kimlik/tazele'
                && $istek['subject'] === $subject
                && $istek->header('X-Kimlik-Imza')[0] === hash_hmac('sha256', $subject, 'bildirim-sirri');
        });
    }

    /**
     * Rol listesi KISITLI istemciye, kapsamında olmayan kişinin uuid'i gitmez.
     *
     * Rolü listeden ÇIKARILAN kişi için ise (eski rol kapsamdaydı) gider:
     * proje onu kapatabilmeli. Listesi boş istemci eskisi gibi herkesi alır.
     */
    #[Test]
    public function kisitli_istemciye_yalnizca_kapsamdaki_kisinin_bildirimi_gider(): void
    {
        Http::fake();

        $network = Rol::where('slug', 'network')->value('id');
        $teknik = Rol::where('slug', 'teknik')->value('id');

        $kisitli = $this->istemci('https://kisitli.ornek.test');
        $kisitli->roller()->attach($network);
        $this->istemci('https://serbest.ornek.test');

        /* Kapsam dışı kişi: kısıtlıya gitmez, serbeste gider. */
        (new KimlikDegisikliginiBildirJob((string) Str::uuid(), $teknik, $teknik, true))->handle();

        Http::assertSent(fn ($istek): bool => str_starts_with($istek->url(), 'https://serbest.ornek.test'));
        Http::assertNotSent(fn ($istek): bool => str_starts_with($istek->url(), 'https://kisitli.ornek.test'));

        /* Kapsamdan ÇIKARILAN kişi (eski rol kapsamdaydı): kısıtlıya da gider. */
        (new KimlikDegisikliginiBildirJob((string) Str::uuid(), $network, $teknik, true))->handle();

        Http::assertSent(fn ($istek): bool => str_starts_with($istek->url(), 'https://kisitli.ornek.test'));
    }

    /** Eski biçimli (rol bilgisiz) iş kuyrukta bekliyorsa herkese gitmeye devam eder. */
    #[Test]
    public function rol_bilgisiz_eski_is_herkese_gider(): void
    {
        Http::fake();

        $this->istemci('https://kisitli.ornek.test')->roller()->attach(Rol::where('slug', 'network')->value('id'));

        (new KimlikDegisikliginiBildirJob((string) Str::uuid()))->handle();

        Http::assertSent(fn ($istek): bool => str_starts_with($istek->url(), 'https://kisitli.ornek.test'));
    }

    /** Rol değişince eski ve yeni rol işe taşınır. */
    #[Test]
    public function degisiklik_olayi_eski_ve_yeni_rolu_tasir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->rol('teknik')->create();
        $eski = $kullanici->rol_id;
        $yeni = Rol::where('slug', 'muhasebe')->value('id');
        $kullanici->update(['rol_id' => $yeni]);

        Queue::assertPushed(
            KimlikDegisikliginiBildirJob::class,
            fn (KimlikDegisikliginiBildirJob $is): bool => $is->kapsamli && $is->eskiRol === $eski && $is->yeniRol === $yeni,
        );
    }

    /** Sırrı olmayan istemciye bildirim GİTMEZ; o proje çekme turuyla öğrenir. */
    #[Test]
    public function sirsiz_istemciye_bildirim_gonderilmez(): void
    {
        Http::fake();

        $this->istemci()->forceFill(['bildirim_sirri' => null])->save();

        (new KimlikDegisikliginiBildirJob((string) Str::uuid()))->handle();

        Http::assertNothingSent();
    }

    /**
     * ERİŞİMİ KALKAN HESABIN AÇIK OTURUMU VE JETONLARI DA KAPANIR.
     *
     * Durumu değiştirmek yalnızca "bir daha giremesin" demekti; açık merkez
     * oturumuyla projelere yeni giriş bile yapılabiliyordu.
     */
    #[Test]
    public function askiya_alinan_hesabin_jetonlari_iptal_edilir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->create(['durum' => Durum::Etkin]);
        $istemci = $this->istemci();

        $jeton = new Token;
        $jeton->forceFill([
            'id' => Str::random(40),
            'user_id' => $kullanici->getKey(),
            'client_id' => $istemci->getKey(),
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ])->save();

        $kullanici->durumuAyarla(Durum::Askida);

        $this->assertTrue((bool) Token::find($jeton->id)->revoked, 'Jeton iptal edilmeliydi.');
    }

    /** Yenileme jetonu da iptal edilir; yoksa istemci onunla yeni erişim jetonu basardı. */
    #[Test]
    public function askiya_alinan_hesabin_yenileme_jetonlari_da_iptal_edilir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->create(['durum' => Durum::Etkin]);
        $istemci = $this->istemci();

        $jeton = new Token;
        $jeton->forceFill([
            'id' => Str::random(40), 'user_id' => $kullanici->getKey(), 'client_id' => $istemci->getKey(),
            'scopes' => [], 'revoked' => false, 'expires_at' => now()->addDay(),
        ])->save();

        $yenileme = new RefreshToken;
        $yenileme->forceFill([
            'id' => Str::random(40), 'access_token_id' => $jeton->id, 'revoked' => false,
            'expires_at' => now()->addMonth(),
        ])->save();

        $baskasi = User::factory()->create();
        $baskasininJetonu = new Token;
        $baskasininJetonu->forceFill([
            'id' => Str::random(40), 'user_id' => $baskasi->getKey(), 'client_id' => $istemci->getKey(),
            'scopes' => [], 'revoked' => false, 'expires_at' => now()->addDay(),
        ])->save();
        $baskasininYenilemesi = new RefreshToken;
        $baskasininYenilemesi->forceFill([
            'id' => Str::random(40), 'access_token_id' => $baskasininJetonu->id, 'revoked' => false,
            'expires_at' => now()->addMonth(),
        ])->save();

        $kullanici->durumuAyarla(Durum::Askida);

        $this->assertTrue((bool) RefreshToken::find($yenileme->id)->revoked, 'Yenileme jetonu iptal edilmeliydi.');
        $this->assertFalse((bool) RefreshToken::find($baskasininYenilemesi->id)->revoked, 'Başkasının jetonuna dokunulmamalı.');
    }

    #[Test]
    public function etkinlestirilen_hesabin_jetonlari_iptal_edilmez(): void
    {
        Queue::fake();

        $kullanici = User::factory()->create(['durum' => Durum::Beklemede]);
        $istemci = $this->istemci();

        $jeton = new Token;
        $jeton->forceFill([
            'id' => Str::random(40),
            'user_id' => $kullanici->getKey(),
            'client_id' => $istemci->getKey(),
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ])->save();

        $kullanici->durumuAyarla(Durum::Etkin);

        $this->assertFalse((bool) Token::find($jeton->id)->revoked);
    }
}
