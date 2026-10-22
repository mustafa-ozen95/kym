<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Models\Group;
use App\Models\System;

/**
 * config/permissions.php'nin okunması.
 *
 * Yetki ekranı, rol şablonu ve Gate tanımları AYNI listeden beslenir; yeni
 * bir yetki eklemek için config dosyasına bir satır yazmak yeterlidir.
 */
class PermissionDictionary
{
    /** @var list<string>|null */
    private ?array $yetenekler = null;

    /**
     * Bütün yetenekler: "sistem.gor", "kullanici.onayla", ...
     *
     * @return list<string>
     */
    public function yetenekler(): array
    {
        if ($this->yetenekler !== null) {
            return $this->yetenekler;
        }

        $liste = [];

        foreach ($this->kaynaklar() as $kaynak => $tanim) {
            foreach (array_keys((array) ($tanim['actions'] ?? [])) as $aksiyon) {
                $liste[] = $kaynak.'.'.$aksiyon;
            }
        }

        return $this->yetenekler = $liste;
    }

    /**
     * Bu yetenek MERKEZİN YÖNETİMİNE mi ait?
     *
     * İKİ AİLE VAR ve karıştırılmamalı:
     *
     *   · YÖNETİM — config/permissions.php'deki kaynaklar (sistem, grup,
     *     kullanici, ayar, istemci, kayit, rol). "Merkezin kendisini kim
     *     yönetir" sorusu.
     *   · ERİŞİM — sistem-erisim.*, grup-erisim.*. Bunlar Hızlı Erişim
     *     sayfasının GÖRÜNÜRLÜĞÜ; yönetimle ilgileri yok ve kayıtlardan
     *     türüyorlar.
     *
     * Ayrımı yazmak zorunlu oldu: yönetim yalnızca yöneticide kalacak ama
     * kutucuk erişimleri rollere verilmeye devam edecek.
     */
    public function yonetimYetkisiMi(string $yetenek): bool
    {
        return in_array($yetenek, $this->yetenekler(), true);
    }

    /**
     * Listeden YÖNETİM yeteneklerini eler.
     *
     * Formdan gelen listeyi süzmek için: ekran onları basmıyor ama istek
     * elle kurulabilir. Yazılan satır bugün hiçbir şey açmazdı, ayar bir gün
     * değiştiğinde sessizce yetkiye dönüşürdü.
     *
     * @param  iterable<int|string, string>  $yetenekler
     * @return list<string>
     */
    public function yonetimiEle(iterable $yetenekler): array
    {
        $kalan = [];

        foreach ($yetenekler as $yetenek) {
            if (! $this->yonetimYetkisiMi($yetenek)) {
                $kalan[] = $yetenek;
            }
        }

        return $kalan;
    }

    /**
     * Yetki ekranlarının bastığı satırlar.
     *
     * Anahtar HAZIR gelir: "sistem" + "." + "sil". Bu birleştirme üç yerde
     * ayrı ayrı yazılıyordu — ikisi Blade'in içinde — ve sözlük değişse biri
     * geride kalırdı. Kural kopyalanmaz, paylaştırılır.
     *
     * @param  bool  $yonetimDahil  Yönetim ailesi (sistem, grup, kullanici,
     *                              ayar, istemci, kayit, rol) listelensin
     *                              mi? Merkezin yönetimi yalnızca
     *                              yöneticideyken listelenmez: işaretlenip
     *                              hiçbir şey açmayan bir kutu,
     *                              işaretleyene yalan söyler. Kutucuk ve
     *                              salon ERİŞİMLERİ her hâlükârda kalır —
     *                              onlar Hızlı Erişim'in görünürlüğü.
     * @return array<string, array{label: string, description: ?string, rows: array<string, string>}>
     */
    public function ekranSatirlari(bool $yonetimDahil = true): array
    {
        $sonuc = [];
        $yonetimKaynaklari = array_keys($this->kaynaklar());

        foreach ($this->tumKaynaklar() as $kaynak => $tanim) {
            if (! $yonetimDahil && in_array($kaynak, $yonetimKaynaklari, true)) {
                continue;
            }

            $satirlar = [];

            foreach ((array) ($tanim['actions'] ?? []) as $aksiyon => $etiket) {
                $satirlar[$kaynak.'.'.$aksiyon] = (string) $etiket;
            }

            $sonuc[$kaynak] = [
                'label' => (string) ($tanim['label'] ?? $kaynak),
                'description' => $tanim['description'] ?? null,
                'rows' => $satirlar,
            ];
        }

        return $sonuc;
    }

    /**
     * Sabit kaynaklar — config'den.
     *
     * @return array<string, array<string, mixed>>
     */
    public function kaynaklar(): array
    {
        return (array) config('permissions.resources', []);
    }

    /**
     * Sabit kaynaklar + KUTUCUKLAR.
     *
     * Hızlı Erişim kutucuklarının yetkileri kayıtlardan türer: yeni bir
     * kutucuk eklendiğinde yetkisi rol ekranında kendiliğinden belirir.
     * Elle config'e satır eklemek gerekseydi, kutucuk ekleyen kişi yetkiyi
     * eklemeyi unutur ve "yetkili" seçeneği sessizce kimseye açılmazdı.
     *
     * AYRI YÖNTEM: bu liste veritabanına gidiyor ve uygulama açılışında
     * çağrılmamalı (göç sırasında tablo henüz yok).
     *
     * @return array<string, array<string, mixed>>
     */
    public function tumKaynaklar(): array
    {
        $kaynaklar = $this->kaynaklar();
        $kutucuklar = $this->kutucukAksiyonlari();

        $salonlar = $this->salonAksiyonlari();

        if ($salonlar !== []) {
            $kaynaklar[Group::RESOURCE] = [
                'label' => 'Hızlı Erişim salonları (erişim)',
                'description' => 'Görünürlüğü “yetkili” olan salonlar. İşaretlenmeyen salon '
                    .'o rol için sayfada HİÇ görünmez; kendi sayfası varsa açılmaz.',
                'actions' => $salonlar,
            ];
        }

        if ($kutucuklar !== []) {
            $kaynaklar[System::RESOURCE] = [
                'label' => 'Hızlı Erişim kutucukları (erişim)',
                'description' => 'Görünürlüğü “yetkili” olan kutucuklar. İşaretlenmeyen kutucuk '
                    .'o rol için sayfada HİÇ görünmez.',
                'actions' => $kutucuklar,
            ];
        }

        return $kaynaklar;
    }

    /**
     * Sabit + kutucuk yetkilerinin tamamı.
     *
     * @return list<string>
     */
    public function tumYetenekler(): array
    {
        $liste = $this->yetenekler();

        foreach (array_keys($this->kutucukAksiyonlari()) as $anahtar) {
            $liste[] = System::RESOURCE.'.'.$anahtar;
        }

        foreach (array_keys($this->salonAksiyonlari()) as $anahtar) {
            $liste[] = Group::RESOURCE.'.'.$anahtar;
        }

        return $liste;
    }

    /**
     * Yetki isteyen kutucuklar: anahtar => ad.
     *
     * @return array<string, string>
     */
    /**
     * Yetki isteyen salonlar: anahtar => ad.
     *
     * @return array<string, string>
     */
    private function salonAksiyonlari(): array
    {
        return Group::where('gorunurluk', System::AUTHORIZED)
            ->orderBy('sira')
            ->pluck('ad', 'anahtar')
            ->all();
    }

    private function kutucukAksiyonlari(): array
    {
        return System::where('gorunurluk', System::AUTHORIZED)
            ->orderBy('sira')
            ->pluck('ad', 'anahtar')
            ->all();
    }

    public function tanirMi(string $yetenek): bool
    {
        return in_array($yetenek, $this->yetenekler(), true);
    }

    /**
     * Tanınmayanları atar.
     *
     * Şablon dosyası elle yazılıyor ve config'den bir aksiyon kaldırıldığında
     * eski satır kayıtlarda kalabiliyor. Süzülmezse ekranda adı olmayan bir
     * kutucuk belirir.
     *
     * @param  iterable<mixed>  $yetenekler
     * @return list<string>
     */
    public function suz(iterable $yetenekler): array
    {
        $temiz = [];

        /* Kutucuk yetkileri de tanınır: rol şablonu onları da taşıyabilir. */
        $taninan = $this->tumYetenekler();

        foreach ($yetenekler as $yetenek) {
            if (is_string($yetenek) && in_array($yetenek, $taninan, true)) {
                $temiz[] = $yetenek;
            }
        }

        return array_values(array_unique($temiz));
    }
}
