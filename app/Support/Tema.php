<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * Gece/gündüz seçimi — SUNUCUDA okunur.
 *
 * NEDEN BURADA: seçim biçemden önce uygulanmalı, yoksa koyu tema kullanan
 * biri sayfanın bir anlığına açık (ya da tersi) yandığını görür. `<head>`
 * içinde satır içi bir betik bu işi yapamaz: içerik CSP'si satır içi
 * betikleri engeller. Çerezi PHP de okuyabildiğine göre doğru cevap
 * sunucudadır: `<html>` etiketi doğru değerle basılır ve
 * ortada düzeltilecek bir şey kalmaz.
 *
 * ÇEREZ ŞİFRELENMEZ ve bu bilinçlidir: onu tarayıcıdaki betik yazıyor
 * (bkz. her paneldeki theme.js) ve okuyan taraf da o. Şifreli bir değeri
 * betik çözemezdi. Taşıdığı bilgi bir sırra değil, bir tercihe ait.
 */
final class Tema
{
    public const COOKIE = 'dc-tema';

    private const GECERLI = ['light', 'dark'];

    /** Varsayılan KOYU: paneller koyu tasarlandı. */
    public const VARSAYILAN = 'dark';

    public static function secilen(): string
    {
        $deger = Request::cookie(self::COOKIE);

        return is_string($deger) && in_array($deger, self::GECERLI, true)
            ? $deger
            : self::VARSAYILAN;
    }
}
