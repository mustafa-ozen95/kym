<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Jobs\KimlikDegisikliginiBildirJob;
use Illuminate\Database\Eloquent\Model;

/**
 * Rol ya da durum değişince BAĞLI PROJELERE haber verilir.
 *
 * NEDEN VAR: projeler merkezi beş dakikada bir yokluyordu; erişimi kaldırılan
 * biri o süre boyunca projede çalışmaya devam edebiliyordu. Bildirim bekleme
 * süresini saniyelere indirir (bkz. KimlikDegisikliginiBildirJob).
 *
 * MODEL OLAYINA bağlanır: durum ve rol birden çok yoldan değişiyor (yönetim
 * ekranı, konsol, kurulum) ve her birine ayrı ayrı eklemek birini unutmaya
 * açık olurdu.
 *
 * YALNIZCA BU İKİ ALAN. Ad ya da parola değişikliği projeleri ilgilendirmiyor;
 * her kaydetmede bildirim atmak gereksiz trafik olurdu.
 */
trait KimlikDegisiminiYayar
{
    public static function bootKimlikDegisiminiYayar(): void
    {
        static::updated(function (Model $model): void {
            if (! $model->wasChanged(['durum', 'rol_id'])) {
                return;
            }

            /*
             * Eski ve yeni rol birlikte gider: bildirim yalnızca kişinin
             * kapsamında olduğu (ya da çıktığı) uygulamalara gitmeli (bkz.
             * KimlikDegisikliginiBildirJob).
             */
            KimlikDegisikliginiBildirJob::dispatch(
                (string) $model->uuid,
                $model->getOriginal('rol_id') === null ? null : (int) $model->getOriginal('rol_id'),
                $model->rol_id === null ? null : (int) $model->rol_id,
                true,
            );
        });

        /*
         * SİLİNEN HESAP: bütün uygulamalara haber gider (kapsam süzülmez).
         * Proje kimliği sorduğunda merkez artık "yok" der; bildirim bunu
         * sonraki çekme turunu beklemeden öğretir ve açık oturum kapanır.
         */
        static::deleted(function (Model $model): void {
            KimlikDegisikliginiBildirJob::dispatch(
                (string) $model->uuid,
                $model->rol_id === null ? null : (int) $model->rol_id,
                null,
                false,
            );
        });
    }
}
