<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * "Kendi kendine kayıt açık mı?" sorusunun TEK yanıtı.
 *
 * VARSAYILAN AÇIK: kayıt güvenli, çünkü hesap YETKİSİZ doğuyor — yönetici
 * etkinleştirene kadar hiçbir sisteme erişemiyor.
 *
 * KAPATILABİLİR olması gerekiyor çünkü her kurulum aynı değil: hesapların
 * yalnızca yönetici eliyle açılması istenen bir kurulumda, açık bir kayıt
 * ekranı onay kuyruğunu yabancı isteklerle doldurur.
 *
 * Kural burada durur, KOPYALANMAZ: kaydı yazan uç ile kayıt bağlantısını
 * çizen ekran aynı yerden geçer. Kopyalansaydı biri kapatılıp diğeri açık
 * kalırdı — ekranda görünen ama çalışmayan bir bağlantı.
 */
class RegistrationOpen
{
    public const SETTING = 'kayit_acik';

    public function acikMi(): bool
    {
        return Setting::bool(self::SETTING, true);
    }
}
