<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Güvenlik başlıkları
|--------------------------------------------------------------------------
| Her yanıta eklenir (bkz. App\Http\Middleware\SecurityHeaders). Değerler
| burada tutulur ki hangi kısıtın neden konduğu tek yerden okunabilsin.
|
| İçerik CSP'si (script-src vb.) BURADA DA VAR. Görünümlerde satır içi
| <script> bloğu yoktur (`script-src 'self'` onları sessizce çalıştırmazdı);
| tema sunucuda çözülür. Politika bağlı panellerle ortaktır.
*/

return [

    'headers' => [

        /*
        | Blade'de satır içi script ve style BIRAKILMADI,
        | bu yüzden 'unsafe-inline' gerekmiyor — gerekseydi politikanın en
        | önemli kısmı anlamını yitirirdi.
        |
        | Tema için de satır içi betik yok: seçim sunucuda okunup <html>
        | etiketine basılıyor (bkz. App\Support\Tema).
        |
        | frame-ancestors 'self': burası kimlik sağlayıcısı ve çerçeveye
        | alınmış bir giriş ekranı, kullanıcının parolayı hangi siteye
        | yazdığını göremediği bir ekrandır.
        |
        | form-action: formlarımız kendi kökenimize gider, AMA giriş formundan
        | başlayan zincir istemcinin adresinde biter (giriş → /oauth/authorize
        | → istemci). Tarayıcı bu kısıtı zincirin TAMAMINA uyguluyor; yalnızca
        | 'self' yazılıyken giriş sessizce iptal oluyordu (bkz.
        | App\Support\IstemciKokenleri). `{istemciler}` yerine kayıtlı
        | uygulamaların kökenleri yazılır — liste elle tutulmaz.
        |
        | Bağlı panellerde aynı politika var; biri değişirse ötekilere de bakın.
        */
        'Content-Security-Policy' => "default-src 'self'; "
            ."script-src 'self'; "
            ."style-src 'self' https://fonts.googleapis.com; "
            ."font-src 'self' https://fonts.gstatic.com; "
            ."img-src 'self' data:; "
            ."connect-src 'self'; "
            ."frame-src 'self'; "
            ."frame-ancestors 'self'; "
            ."base-uri 'self'; "
            ."form-action 'self' {istemciler}; "
            ."object-src 'none'",

        /* Yukarıdakini tanımayan eski tarayıcılar için. */
        'X-Frame-Options' => 'SAMEORIGIN',

        /* Tarayıcı içerik türünü tahmin etmesin. */
        'X-Content-Type-Options' => 'nosniff',

        /*
        | Adres başka kökene taşınmaz. Doğrulama ve OAuth adresleri imzalı
        | jeton taşıyor; dışarı çıkarken yol gitmemeli.
        */
        'Referrer-Policy' => 'same-origin',

        'X-Permitted-Cross-Domain-Policies' => 'none',

        /*
        | HSTS KAPALI — ve BİLEREK max-age=0 GÖNDERİLİYOR.
        |
        | Başlık, tarayıcıya "bu adrese bir daha asla http ile gitme" der ve
        | bunu KENDİ İÇİNDE SAKLAR. Sertifika bir gün düşerse tarayıcı uyarı
        | ekranını bile göstermez: "Yine de devam et" düğmesi HSTS'li adreslerde YOKTUR.
        | Yani yönetici kendi paneline hiçbir şekilde ulaşamaz; sertifika
        | düşerse panele erişim kesilmesin diye HSTS varsayılan kapalı.
        |
        | Başlığı SİLMEK YETMEZ: tarayıcıda zaten yerleşmiş kayıt bir yıl
        | daha yaşar. `max-age=0` o kaydı SİLDİRİR. Değer bu yüzden burada
        | duruyor; kaldırılırsa eski tarayıcılar kilitli kalır.
        |
        | Karşılığında ne kaybediliyor: ilk istek http ile yapılırsa araya
        | girme penceresi. http zaten https'e yönlendiriliyor; panel iç ağda
        | çalışıyorsa bu risk, panele hiç girememe riskinden küçük.
        */
        'Strict-Transport-Security' => 'max-age=0',

        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), '
            .'payment=(), usb=(), magnetometer=(), gyroscope=(), autoplay=()',
    ],
];
