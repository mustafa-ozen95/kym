<?php

declare(strict_types=1);

/**
 * Yetki sözlüğü.
 *
 * MERKEZ YETKİ DAĞITMAZ. Burada yanıtlanan tek soru "merkezin KENDİSİNİ kim
 * yönetir": kutucukları kim düzenler, hesapları kim onaylar, posta ayarını
 * kim değiştirir. Bir kullanıcının hangi projede ne yapabileceğine o proje
 * kendi yetki sistemiyle karar verir; roller merkeze sızarsa modülerlik ilk
 * özel durumda çöker.
 *
 * "Yönetici / kullanıcı" ikilisi bu iş için fazla kabaydı: hesapları
 * onaylayacak birine posta sunucusunun parolasını değiştirme yetkisi de
 * vermek gerekiyordu.
 *
 * Buraya bir aksiyon eklemek yetki ekranına da, rol şablonuna da onu ekler;
 * başka hiçbir yere dokunulmaz.
 */
return [

    /*
     * MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDE.
     *
     * Merkezin yönetim bölümüne yönetici dışında kimse girmez: görüntüleme,
     * ekleme, düzenleme ve silme dahil.
     *
     * Açıkken (varsayılan) aşağıdaki kaynakların hiçbir yeteneği yönetici
     * olmayan bir kullanıcıya verilemez — ne rol şablonuyla ne kişisel
     * istisnayla. Kutucuk ve salon ERİŞİMLERİ (sistem-erisim.*,
     * grup-erisim.*) bundan etkilenmez; onlar Hızlı Erişim sayfasının
     * görünürlüğüdür, yönetim değil.
     *
     * Kapatmak yetki dağıtımını geri açar: ekranlardaki kutucuklar geri
     * gelir, şablon ve istisnalar yeniden yazılabilir. Rollerin şablonları
     * boşaltıldığı için (göç 2026_09_15_210000) kapatmak tek başına kimseye
     * yetki VERMEZ; yetkiler yeniden seçilmelidir.
     *
     * Ortam değişkeniyle de kapatılabilir ama bu bilinçli bir karardır:
     * .env'e erişen kişi zaten sunucuya erişiyor demektir.
     */
    'yonetim_yalnizca_yonetici' => (bool) env('YONETIM_YALNIZCA_YONETICI', true),

    /*
     * Kaynaklar ve üzerlerinde yapılabilecekler.
     *
     * Anahtar "<kaynak>.<aksiyon>" biçiminde bir YETENEĞE dönüşür ve
     * `@can('sistem.duzenle')` gibi sorulur (bkz. YetkiSaglayici).
     */
    'resources' => [

        'sistem' => [
            'label' => 'Hızlı Erişim kutucukları',
            'description' => 'Ana sayfadaki sistem kutucukları ve bağlantıları.',
            'actions' => [
                'gor' => 'Görüntüle',
                'ekle' => 'Ekle',
                'duzenle' => 'Düzenle',
                'sil' => 'Sil',
            ],
        ],

        'grup' => [
            'label' => 'Hızlı Erişim salonları',
            'description' => 'Kutucukların dizildiği bölümler ve kendi sayfaları.',
            'actions' => [
                'gor' => 'Görüntüle',
                'ekle' => 'Ekle',
                'duzenle' => 'Düzenle',
                'sil' => 'Sil',
            ],
        ],

        'kullanici' => [
            'label' => 'Kullanıcılar',
            'description' => 'Hesapların onaylanması, askıya alınması ve rolleri.',
            'actions' => [
                'gor' => 'Görüntüle',
                'ekle' => 'Hesap aç',
                'duzenle' => 'Bilgilerini düzenle (ad, e-posta, parola)',
                'onayla' => 'Durum değiştir (onayla / askıya al)',

                /*
                 * ROL VERMEK AYRI BİR YETKİDİR.
                 *
                 * "Kullanıcıları yönet", kendini yönetici yapma yetkisi
                 * değildir. Hesapları onaylayan kişi ile yetki dağıtan kişi
                 * aynı olmak zorunda değil.
                 */
                'rol' => 'Rol ata',
            ],
        ],

        'ayar' => [
            'label' => 'Ayarlar',
            'description' => 'Giriş kuralları ve posta sunucusu.',
            'actions' => [
                'gor' => 'Görüntüle',
                'duzenle' => 'Değiştir',

                /*
                 * Deneme iletisi DIŞARIYA ÇIKAR: gerçekten posta gönderir.
                 * "Ayarı görüntüle" bunu kapsamaz.
                 */
                'posta-dene' => 'Deneme iletisi gönder',

                /*
                 * SERTİFİKA TURU: düğme sunucuda bir işlem başlatır (yeni
                 * doğrulama kaydı üretir ya da sertifikayı alır). Ayar
                 * değiştirmekten AYRI bir yetki — panelin adresleriyle
                 * ilgili, geri alınması dışarıdan bağlı bir iş.
                 */
                'sertifika' => 'Sertifika turu başlat / tamamla',

                /*
                 * VERİTABANI: merkezin veritabanının adresini, kullanıcısını
                 * ve boyutunu gösterir; taşı düğmesi konteyneri yeniden
                 * başlatıp veriyi başka bir sunucuya kopyalar. Merkez kapalı
                 * kaldığı sürece hiçbir panele girilemez — "ayarı görüntüle"
                 * bunu kapsamaz, ekranı görmek bile bu yetkiyi ister.
                 */
                'veritabani' => 'Veritabanını görüntüle / taşı',

                /*
                 * GÜNCELLEME: yeni sürüm var mı (yalnızca bakar, GitHub'a
                 * sorar). Sonuç güncellemeyi başlatacak kişiyi ilgilendirir;
                 * yeni sürüm uyarısını da yalnız bu yetkisi olan görür.
                 */
                'guncelleme' => 'Güncelleme durumunu görüntüle / kontrol et',
            ],
        ],

        /*
         * BAĞLI UYGULAMALAR.
         *
         * Yeni bir siteyi merkeze bağlamak, o siteye "bu kullanıcı kim"
         * sorusunu sorma hakkı vermektir; yani yetki dağıtmakla aynı
         * ağırlıkta bir iş. Ayrı kaynak olması bu yüzden: "kutucukları
         * düzenle" yetkisi yeni bir uygulama bağlamayı KAPSAMAZ.
         */
        'istemci' => [
            'label' => 'Bağlı uygulamalar',
            'description' => 'Merkez ile giriş yapabilen projeler ve dönüş adresleri.',
            'actions' => [
                'gor' => 'Görüntüle',
                'ekle' => 'Yeni uygulama bağla',
                'duzenle' => 'Düzenle',

                /*
                 * Sır YENİLEMEK, uygulamanın girişini o an durdurur (eski sır
                 * geçersiz olur). Düzenlemekten ayrı bir yetki.
                 */
                'sir' => 'Sırrı yenile',
                'sil' => 'Bağlantıyı kaldır',
            ],
        ],

        /*
         * DENETİM KAYDI.
         *
         * Kaydı okumak ayrı bir yetkidir: "kim nereye girdi" satırları, kimin
         * hangi sistemi kullandığını gösterir ve bu tek başına bilgidir.
         * Doğrulama da ayrı — zincirin bütünlüğünü sınamak, kaydı okumaktan
         * farklı bir sorumluluktur.
         */
        'kayit' => [
            'label' => 'Denetim kaydı',
            'description' => 'Kim ne yaptı, kim nereye giriş yaptı.',
            'actions' => [
                'gor' => 'Görüntüle',
                'dogrula' => 'Zinciri doğrula',
            ],
        ],

        'rol' => [
            'label' => 'Roller',
            'description' => 'Rollerin kendisi ve hazır yetki şablonları.',
            'actions' => [
                'gor' => 'Görüntüle',
                'duzenle' => 'Düzenle',
            ],
        ],
    ],

    /*
     * Yerleşik rollerin HAZIR ŞABLONLARI — ŞU AN HEPSİ BOŞ.
     *
     * MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDE (bkz. yukarıdaki ayar).
     *
     * Bu liste tek başına o kararı TAŞIYAMAZ — bir kutucuk işaretlenince
     * geri açılırdı. Karar kodda duruyor:
     * YetkiCozumleyici::YONETIM_YALNIZCA_YONETICI. Buradaki şablonlar onunla
     * aynı şeyi söylesin diye boş; ayrışsalardı ekranda "var" görünen bir
     * yetki hiçbir işe yaramazdı.
     *
     * Yukarıdaki SÖZLÜK SİLİNMEDİ: yetki dağıtımı yeniden açılmak
     * istendiğinde (sabiti false yapmak) kaynaklar, aksiyonlar ve ekranlar
     * olduğu gibi yerinde duruyor.
     *
     * Yönetici rolü buraya yazılmaz: bütün kontrolleri geçer (bkz.
     * YetkiSaglayici'daki Gate::before).
     */
    'templates' => [

        /*
         * Yerleşik örnek roller. Boş şablon bir eksiklik DEĞİL: bu rollerin
         * işi, kime hangi kutucuğun açılacağını gruplamak. Kutucuk ve salon
         * ERİŞİMLERİ bu listeden gelmiyor (sistem-erisim.*, grup-erisim.*);
         * onlar Hızlı Erişim sayfasının görünürlüğüdür, yönetim yetkisiyle
         * ilgileri yoktur.
         */
        'network' => [],
        'teknik' => [],
        'muhasebe' => [],
    ],
];
