<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| İstek Sınırları (dakikada kaç istek)
|--------------------------------------------------------------------------
|
| Sayılar koda gömülmez: görsel test koşusu onlarca senaryoda art arda giriş
| dener ve sabit bir sınır o koşuyu kodla ilgisiz biçimde düşürür.
| Varsayılanlar ÜRETİMDEKİ değerlerdir.
|
*/

return [
    /*
     * Girişte dakikada izin verilen istek sayısı (adres başına). 0 = SINIR YOK
     * (ara katman hiç eklenmez; bkz. App\Support\IstekSiniri).
     *
     * Varsayılan KAPALI: sınır gündelik kullanımda insanı dışarıda
     * bırakabilir; gerekiyorsa .env ile açılır. Bağlı panellerde de aynı
     * varsayılan var.
     *
     * Kapalıyken parola denemesinin önünde bu uçta fren kalmaz; koruma
     * dışarıdan gelmelidir (ters vekil, güvenlik duvarı, ağın kendisi).
     * Dışarı açılacaksa bir sayı verin — 10 eski varsayılandı.
     *
     * İKİNCİ ADIM SINIRI AYRI ve KAPATILMADI (aşağıda, iki_adim): altı
     * hanelik bir kod sınırsız denemeye bırakılamaz.
     */
    'giris' => (int) env('LIMIT_GIRIS', 0),

    /*
     * En kısa parola uzunluğu.
     *
     * Varsayılan sekiz karakter. Kısa parolanın
     * riskini ikinci adım (TOTP) karşılıyor: parola tek başına yeterli değil.
     * Kayıt ekranı ve konsol komutu AYNI değeri kullanır; iki yere ayrı
     * yazılsaydı biri değişince diğeri sessizce eski kalırdı.
     */
    'parola_uzunluk' => (int) env('LIMIT_PAROLA_UZUNLUK', 8),

    /*
     * İkinci adım kodu denemesi.
     *
     * Altı hanelik bir kod, sınırsız deneme verilirse dakikalar içinde
     * bulunur; ikinci adımın anlamı kalmaz. Sayaç kullanıcı başına tutulur
     * ve beş dakika sonra düşer.
     */
    'iki_adim' => (int) env('LIMIT_IKI_ADIM', 5),
    'kayit' => (int) env('LIMIT_KAYIT', 5),

    /*
     * Doğrulama postasının yeniden gönderimi.
     *
     * Uç oturumsuz çağrılıyor: sınırsız bırakılsaydı hem kimin kayıtlı
     * olduğunu sorgulamanın hem de bir posta kutusunu doldurmanın yolu
     * olurdu. Sayaç adres başına değil IP başına tutulur ve on dakikada
     * düşer.
     */
    'dogrulama_postasi' => (int) env('LIMIT_DOGRULAMA_POSTASI', 3),
];
