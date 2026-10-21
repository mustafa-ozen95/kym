<?php

declare(strict_types=1);

namespace App\Services\Permission;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Ayarlar'ın sekmeleri.
 *
 * Roller ayrı bir üst menü öğesi değildir: kullanıcı onu Ayarlar'ın altında
 * arar. Bağlı panellerde de aynı düzen var — paneller arasında geçen kişi
 * aynı şeyi iki farklı yerde aramasın.
 *
 * Şerit TEK LİSTEDEN beslenir; sekmelerden birine yetkisi olmayan onu hiç
 * görmez (erişemeyeceği bir bağlantı 403 üretmekten başka işe yaramaz).
 */
class SettingSections
{
    /** @var list<array{label: string, route: string, yetki: string}> */
    private const BOLUMLER = [
        ['label' => 'Genel', 'route' => 'yonetim.ayarlar', 'yetki' => 'ayar.gor'],
        ['label' => 'Roller', 'route' => 'yonetim.roller', 'yetki' => 'rol.gor'],
        ['label' => 'Denetim kaydı', 'route' => 'yonetim.kayitlar', 'yetki' => 'kayit.gor'],
        ['label' => 'Sertifika', 'route' => 'yonetim.sertifika', 'yetki' => 'ayar.gor'],
        ['label' => 'Veritabanı', 'route' => 'yonetim.veritabani', 'yetki' => 'ayar.veritabani'],
        ['label' => 'Güncelleme', 'route' => 'yonetim.guncelleme', 'yetki' => 'ayar.guncelleme'],
    ];

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function serit(?string $etkin = null): array
    {
        $etkin ??= Route::currentRouteName();

        $liste = [];

        foreach (self::BOLUMLER as $bolum) {
            if (! Gate::allows($bolum['yetki'])) {
                continue;
            }

            $liste[] = [
                'label' => $bolum['label'],
                'url' => route($bolum['route']),

                /* Rol ayrıntı sayfası da "Roller" sekmesini işaretli tutar. */
                'active' => str_starts_with((string) $etkin, $bolum['route']),
            ];
        }

        return $liste;
    }

    /** Ayarlar'a girebilir mi? En az bir sekmesi olan girer. */
    public function girebilirMi(): bool
    {
        return $this->serit() !== [];
    }

    /**
     * Üst menüdeki "Ayarlar" bağlantısının gideceği yer.
     *
     * Sabit olarak "Genel"e gitseydi, yalnızca rol yetkisi olan biri menüde
     * gördüğü bağlantıya tıkladığında 403 alırdı. Açabildiği İLK sekmeye
     * gider.
     */
    public function ilkAdres(): ?string
    {
        return $this->serit()[0]['url'] ?? null;
    }
}
