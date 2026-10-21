<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kimlik sunucusu
|--------------------------------------------------------------------------
|
| Bu merkez API erişimi dağıtmıyor, KİMLİK kanıtlıyor: istemci jetonu alır,
| kullanıcı bilgisini bir kez okur ve kendi oturumunu açar. Bu yüzden jeton
| ömürleri kısa tutulur — uzun ömürlü bir jeton, çalındığında uzun süre
| geçerli olur.
|
*/

return [
    'jeton_dakika' => (int) env('KIMLIK_JETON_DAKIKA', 10),
    'yenileme_gun' => (int) env('KIMLIK_YENILEME_GUN', 1),

    /* Yayıncı adı: doğrulayıcı uygulamalarda ve onay ekranında görünür. */
    'yayinci' => env('KIMLIK_YAYINCI', 'Kimlik Yönetim Merkezi'),
];
