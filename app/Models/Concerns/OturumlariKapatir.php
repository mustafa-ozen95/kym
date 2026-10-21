<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Durum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Erişimi kalkan hesabın AÇIK OTURUMLARI ve JETONLARI da kapanır.
 *
 * NEDEN VAR: durumu değiştirmek yalnızca "bir daha giremesin" demekti. O anda
 * açık olan merkez oturumu çalışmaya devam ediyor, üstelik o oturumla
 * projelere yeni giriş bile yapılabiliyordu — kaldırma işleminin amacının tam
 * tersi.
 *
 * İKİ ŞEY BİRDEN kapatılır:
 *
 *   - Merkezdeki oturum satırları (SESSION_DRIVER=database).
 *   - Passport JETONLARI. Jeton kalırsa projeler o kimlikle konuşmaya devam
 *     eder; oturumu kapatıp jetonu bırakmak kapıyı kilitleyip anahtarı
 *     kapıda unutmaktır.
 *
 * Projelerdeki oturumlar buradan kapatılmaz — onları her panel kendi
 * kapatıyor (bkz. panellerde aynı adlı özellik ve KimlikEsitleyici).
 *
 * MODEL OLAYINA bağlanır, tek tek ekranlara değil: durum birden çok yoldan
 * değişiyor ve hepsine ayrı ayrı eklemek birini unutmaya açık olurdu.
 */
trait OturumlariKapatir
{
    public static function bootOturumlariKapatir(): void
    {
        static::updated(function (Model $model): void {
            if (! $model->wasChanged('durum')) {
                return;
            }

            if ($model->durum instanceof Durum && $model->durum->girebilir()) {
                return;
            }

            $model->oturumlariKapat();
        });
    }

    /** Açık oturumları siler ve jetonları iptal eder; kaç oturum kapandığını döndürür. */
    public function oturumlariKapat(): int
    {
        Token::where('user_id', $this->getKey())->where('revoked', false)->update(['revoked' => true]);

        /*
         * YENİLEME JETONLARI DA İPTAL EDİLİR.
         *
         * Yalnızca erişim jetonunu iptal etmek yetmez: yenileme jetonu
         * duruyorsa istemci onunla yeni bir erişim jetonu basabilir. Kullanılan
         * ucun kendi kapısı o jetonu yine reddeder ama iptal edilmemiş bir
         * kimlik bilgisini ortada bırakmanın gerekçesi yok. Erişim jetonu
         * zaten iptal edilmiş olabilir (revoked=true); bu yüzden kullanıcının
         * TÜM jetonlarına bakılır.
         */
        RefreshToken::whereIn('access_token_id', Token::where('user_id', $this->getKey())->select('id'))
            ->where('revoked', false)
            ->update(['revoked' => true]);

        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->getKey())
            ->delete();
    }
}
