<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ayar;
use DateTimeZone;

/**
 * Panelden seçilen saat dilimi.
 *
 * Kayıtların, zamanlayıcının ve ekranlardaki bütün tarihlerin hangi dilime
 * göre gösterileceğini belirler. Denetim kaydı takibi için doğru olması şart:
 * yanlış dilim, olayların gerçekte ne zaman olduğunu saatlerce kaydırır.
 *
 * Ayar boşsa `config('app.timezone')` (yani APP_TIMEZONE) geçerli kalır.
 */
class SaatDilimi
{
    public const AYAR = 'saat_dilimi';

    public static function secili(): string
    {
        $deger = Ayar::metin(self::AYAR);

        return $deger !== null && $deger !== '' ? $deger : (string) config('app.timezone', 'UTC');
    }

    /** @return array<string, string> */
    public static function liste(): array
    {
        $kimlikler = DateTimeZone::listIdentifiers();

        return array_combine($kimlikler, $kimlikler);
    }

    public static function gecerliMi(string $deger): bool
    {
        return in_array($deger, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Seçimi gerçekten uygular.
     *
     * config'e yazmak YETMEZ: Laravel PHP'nin varsayılan dilimini AÇILIŞTA bir
     * kez kuruyor ve sonraki config değişikliği ona ulaşmıyor. Yalnızca config
     * güncellenseydi ayar kaydedilmiş görünür, bütün tarihler eski dilimde
     * kalırdı.
     */
    public static function uygula(): void
    {
        $dilim = self::secili();

        if (! self::gecerliMi($dilim)) {
            return;
        }

        config(['app.timezone' => $dilim]);

        if (date_default_timezone_get() !== $dilim) {
            date_default_timezone_set($dilim);
        }
    }
}
