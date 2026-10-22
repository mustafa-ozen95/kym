<?php

declare(strict_types=1);

namespace App\Services\Permission;

use Illuminate\Support\Facades\Gate;

/**
 * Yönetim bölümleri ve hangi yetkiyi istedikleri.
 *
 * TEK LİSTE: üst menü de, Hızlı Erişim'deki "Yönetim" bağlantısı da buradan
 * besleniyor. İki yerde ayrı yazılsaydı biri değişince diğeri sessizce eski
 * kalır ve kullanıcı, açamayacağı bir bölüme gönderilirdi.
 */
class AdminSections
{
    /** @var list<array{label: string, route: string, yetki: ?string}> */
    private const SECTIONS = [
        ['label' => 'Sistemler', 'route' => 'admin.systems', 'yetki' => 'sistem.gor'],
        ['label' => 'Salonlar', 'route' => 'admin.groups', 'yetki' => 'grup.gor'],
        ['label' => 'Kullanıcılar', 'route' => 'admin.users', 'yetki' => 'kullanici.gor'],
        ['label' => 'Uygulamalar', 'route' => 'admin.clients', 'yetki' => 'istemci.gor'],
        ['label' => 'Hızlı Erişim', 'route' => 'quick-access', 'yetki' => null],
        /*
         * Ayarlar sonda: panellerin ortak standardı.
         *
         * Roller burada DEĞİL, Ayarlar'ın bir sekmesi (bkz.
         * SettingSections). Bu yüzden kapı "ayar.gor" değil,
         * sekmelerden herhangi biri: yalnızca rol yetkisi olan biri de
         * Ayarlar'a girebilmeli.
         */
        ['label' => 'Ayarlar', 'route' => 'admin.settings', 'yetki' => 'ayarlar-serit'],
    ];

    /**
     * Kullanıcının GÖREBİLDİĞİ bölümler.
     *
     * @return list<array{label: string, route: string}>
     */
    public function gorunen(): array
    {
        $liste = [];

        foreach (self::SECTIONS as $bolum) {
            if ($this->gorebilirMi($bolum['yetki'])) {
                $liste[] = [
                    'label' => $bolum['label'],
                    'route' => $bolum['route'],

                    /*
                     * Ayarlar, kullanıcının AÇABİLDİĞİ ilk sekmesine gider.
                     * Sabit "Genel" olsaydı, yalnızca rol yetkisi olan biri
                     * menüde gördüğü bağlantıda 403 alırdı.
                     */
                    'url' => $bolum['yetki'] === 'ayarlar-serit'
                        ? (string) app(SettingSections::class)->ilkAdres()
                        : route($bolum['route']),
                ];
            }
        }

        return $liste;
    }

    /** Ayarlar'ın kapısı tek bir yetki değil, sekmelerinden herhangi biri. */
    private function gorebilirMi(?string $yetki): bool
    {
        if ($yetki === null) {
            return true;
        }

        return $yetki === 'ayarlar-serit'
            ? app(SettingSections::class)->girebilirMi()
            : Gate::allows($yetki);
    }

    /**
     * "Yönetim" bağlantısının gideceği yer: AÇABİLECEĞİ ilk bölüm.
     *
     * Sabit bir adrese göndermek, o bölüme yetkisi olmayan herkesi 403'e
     * götürüyordu — üstelik menüde yetkisi olan başka bölümler dururken.
     */
    public function ilkAdres(): ?string
    {
        foreach ($this->gorunen() as $bolum) {
            if ($bolum['route'] !== 'quick-access') {
                return $bolum['url'];
            }
        }

        return null;
    }
}
