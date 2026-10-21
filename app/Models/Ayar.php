<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Ekrandan değiştirilebilen ayarlar.
 *
 * Okuma ÖNBELLEKLENİR: her istekte veritabanına gitmesi gereken bir değer
 * değil, ama değiştiği anda geçerli olmalı — bu yüzden yazma önbelleği
 * temizler.
 */
class Ayar extends Model
{
    protected $table = 'ayarlar';

    protected $primaryKey = 'anahtar';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['anahtar', 'deger'];

    private const ONBELLEK = 'ayarlar';

    /** @return array<string, string|null> */
    public static function hepsi(): array
    {
        return Cache::rememberForever(self::ONBELLEK, fn (): array => self::pluck('deger', 'anahtar')->all());
    }

    public static function bool(string $anahtar, bool $varsayilan = false): bool
    {
        $deger = self::hepsi()[$anahtar] ?? null;

        return $deger === null ? $varsayilan : (bool) (int) $deger;
    }

    public static function metin(string $anahtar, ?string $varsayilan = null): ?string
    {
        $deger = self::hepsi()[$anahtar] ?? null;

        return $deger === null || $deger === '' ? $varsayilan : $deger;
    }

    public static function yaz(string $anahtar, string|bool|int|null $deger): void
    {
        self::updateOrCreate(
            ['anahtar' => $anahtar],
            ['deger' => is_bool($deger) ? (string) (int) $deger : (string) $deger],
        );

        Cache::forget(self::ONBELLEK);
    }
}
