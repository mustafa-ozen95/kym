<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Hesabın durumu.
 *
 * Kayıt açıktır ama yeni hesap BEKLEMEDE doğar ve hiçbir yere erişemez;
 * yönetici etkinleştirene kadar öylece durur. Aksi hâlde internetten kayıt
 * olan biri, merkezin kapısında meşru bir kimlik elde etmiş olurdu.
 */
enum Status: string
{
    case Beklemede = 'beklemede';
    case Etkin = 'etkin';
    case Askida = 'askida';

    public function label(): string
    {
        return match ($this) {
            self::Beklemede => 'Onay bekliyor',
            self::Etkin => 'Etkin',
            self::Askida => 'Askıda',
        };
    }

    /** Rozet rengi; durumun kendisi söyler. */
    public function badge(): BadgeVariant
    {
        return match ($this) {
            self::Etkin => BadgeVariant::Green,
            self::Beklemede => BadgeVariant::Yellow,
            self::Askida => BadgeVariant::Red,
        };
    }

    /** Bu durumdaki hesap giriş yapabilir mi? */
    public function girebilir(): bool
    {
        return $this === self::Etkin;
    }
}
