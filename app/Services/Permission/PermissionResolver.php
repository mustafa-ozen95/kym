<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Models\User;
use App\Models\UserPermission;

/**
 * "Bu kullanıcı bunu yapabilir mi?" sorusunun TEK yanıtı.
 *
 * SIRA ÖNEMLİ:
 *
 *  1. Yönetici rolü her şeyi geçer.
 *  2. Kişisel RED, şablonun verdiğini geri alır. Red önce sorulur; yoksa
 *     "hem izin hem red" satırı olan bir kullanıcı için sonuç sıralamaya
 *     kalırdı.
 *  3. Kişisel İZİN.
 *  4. Rolün şablonu.
 *
 * Sonuç istek başına önbelleklenir: tek bir ekran onlarca kez soruyor ve her
 * seferinde iki sorgu atmanın anlamı yok.
 *
 * AMA ÖNCE: yönetim ailesi yalnızca yöneticide. Bkz. yalnizcaYonetici().
 */
class PermissionResolver
{
    /**
     * MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDE (varsayılan: AÇIK).
     *
     * Merkezin yönetim bölümüne yönetici dışında kimse girmez: görüntüleme,
     * ekleme, düzenleme ve silme dahil.
     *
     * Yalnızca rol şablonlarını boşaltmak bu kararı VERİYE yazardı; bir
     * kutucuk işaretlenince sessizce geri açılırdı. Karar burada, çözümün
     * içinde duruyor ve kişisel izin/red ile rol şablonunun önüne geçiyor.
     *
     * YALNIZCA YÖNETİM AİLESİNİ kapatır. Kutucuk ve salon erişimleri
     * (sistem-erisim.*, grup-erisim.*) rollere verilmeye devam eder; onlar
     * Hızlı Erişim sayfasının görünürlüğüdür (bkz.
     * PermissionDictionary::yonetimYetkisiMi).
     *
     * Katmanlı çözüm SİLİNMEDİ: yetki dağıtımı yeniden açılmak istenirse
     * ayarı false yapmak yeterli — şablonlar, istisnalar ve ekranlar yerinde
     * duruyor. Silmek geri dönüşü olmayan bir karardı; bu geri dönülebilir.
     * Açılırsa yapılacak ikinci iş: rollerin şablonlarını yeniden vermek
     * (göç 2026_09_15_210000 hepsini boşalttı).
     *
     * Ayar olarak durmasının ikinci nedeni: dağıtım düzeneğinin sınamaları
     * bu ayarı kapatıp koşuyor. Sabit olsaydı düzenek sınanamaz hâle gelir
     * ve "kapalı" olduğu için çürüdüğünü kimse fark etmezdi.
     */
    private function yalnizcaYonetici(): bool
    {
        return (bool) config('permissions.yonetim_yalnizca_yonetici', true);
    }

    /**
     * Yetki dağıtımı şu an açık mı?
     *
     * Ekranlar bunu sorar: kapalıyken "Hazır yetkiler" ve "kişisel
     * istisnalar" bölümleri kutucuk göstermez, neden göstermediğini yazar.
     * Etkisiz bir kutucuk göstermek, işaretleyen kişiye yalan söylemektir.
     */
    public static function yetkiDagitilabilirMi(): bool
    {
        return ! (bool) config('permissions.yonetim_yalnizca_yonetici', true);
    }

    /** @var array<int, array{izin: list<string>, red: list<string>, sablon: list<string>, yonetici: bool}> */
    private array $onbellek = [];

    public function __construct(private PermissionDictionary $sozluk) {}

    public function yapabilirMi(User $kullanici, string $yetenek): bool
    {
        $kayit = $this->kayit($kullanici);

        if ($kayit['yonetici']) {
            return true;
        }

        /*
         * YÖNETİM YETKİSİ YÖNETİCİ OLMAYANA VERİLMEZ.
         *
         * Yalnızca yönetim ailesi kapanır; kutucuk ve salon erişimleri
         * (sistem-erisim.*, grup-erisim.*) rollere verilmeye devam eder —
         * onlar Hızlı Erişim sayfasının görünürlüğü, yönetim değil.
         */
        if ($this->yalnizcaYonetici() && $this->sozluk->yonetimYetkisiMi($yetenek)) {
            return false;
        }

        if (in_array($yetenek, $kayit['red'], true)) {
            return false;
        }

        return in_array($yetenek, $kayit['izin'], true)
            || in_array($yetenek, $kayit['sablon'], true);
    }

    /**
     * Kullanıcının etkin yetkileri — ekranlarda göstermek için.
     *
     * @return list<string>
     */
    public function yetenekler(User $kullanici): array
    {
        $kayit = $this->kayit($kullanici);

        if ($kayit['yonetici']) {
            return $this->sozluk->yetenekler();
        }

        $etkin = array_diff(array_merge($kayit['sablon'], $kayit['izin']), $kayit['red']);

        if ($this->yalnizcaYonetici()) {
            $etkin = array_filter($etkin, fn (string $y): bool => ! $this->sozluk->yonetimYetkisiMi($y));
        }

        return array_values($etkin);
    }

    /**
     * Yönetim ekranlarının herhangi birine girebilir mi?
     *
     * YALNIZCA YÖNETİM AİLESİNE bakar, "hiç yetkisi var mı" diye sormaz:
     * yoksa kutucuk erişimi (sistem-erisim.*) verilen bir rol de bu kapıdan
     * geçerdi — oysa o yetki Hızlı Erişim sayfasının görünürlüğüdür,
     * yönetimle ilgisi yoktur.
     */
    public function yonetimeGirebilirMi(User $kullanici): bool
    {
        foreach ($this->yetenekler($kullanici) as $yetenek) {
            if ($this->sozluk->yonetimYetkisiMi($yetenek)) {
                return true;
            }
        }

        return false;
    }

    public function unut(User $kullanici): void
    {
        unset($this->onbellek[(int) $kullanici->getKey()]);
    }

    /** @return array{izin: list<string>, red: list<string>, sablon: list<string>, yonetici: bool} */
    private function kayit(User $kullanici): array
    {
        $anahtar = (int) $kullanici->getKey();

        if (isset($this->onbellek[$anahtar])) {
            return $this->onbellek[$anahtar];
        }

        $kullanici->loadMissing('rol');

        $satirlar = UserPermission::where('user_id', $kullanici->getKey())->get();

        return $this->onbellek[$anahtar] = [
            'yonetici' => (bool) $kullanici->rol?->is_admin,
            'sablon' => $this->sozluk->suz($kullanici->rol?->yetenekler() ?? []),
            'izin' => $this->sozluk->suz($satirlar->where('etki', UserPermission::IZIN)->pluck('yetenek')),
            'red' => $this->sozluk->suz($satirlar->where('etki', UserPermission::RED)->pluck('yetenek')),
        ];
    }
}
