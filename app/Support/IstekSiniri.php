<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rota için istek sınırı ara katmanı — ya da hiç.
 *
 * SIFIR "SINIR YOK" DEMEKTİR ve bunun için ara katman HİÇ EKLENMEZ:
 * `throttle:0,1` yazmak sınırı kaldırmaz, dakikada SIFIR isteğe indirir ve
 * ucu tamamen kapatır.
 *
 * NEDEN VAR: sınırlar rotalarda doğrudan yazılıyordu —
 * `->middleware('throttle:'.config('limits.giris').',1')` — ve o biçimde
 * sıfırın anlamı yoktu. `LIMIT_GIRIS=0` verip "sınırı kaldırdım" sanan biri
 * giriş ucunu herkese kapatırdı; panele kimse giremezdi ve sebebi ayarın
 * kendisinde görünmezdi.
 *
 * Route::middleware() boş diziyi sorunsuz kabul eder, o yüzden dönüş
 * doğrudan zincire verilebilir.
 *
 * Bağlı panellerde aynı isimde, DENETLEYİCİLER için çalışan bir eşi olabilir
 * (Illuminate\Routing\Controllers\Middleware döndürür). Karar aynı, biçim
 * farklı; biri değişirse ötekine de bakın.
 */
final class IstekSiniri
{
    /**
     * @return array<int, string>
     */
    public static function rotaIcin(string $ad): array
    {
        /* Negatif sayı da "sınır yok" sayılır; eksi bir sınırın anlamı yok. */
        $sinir = max(0, (int) config('limits.'.$ad));

        return $sinir === 0 ? [] : ['throttle:'.$sinir.',1'];
    }
}
