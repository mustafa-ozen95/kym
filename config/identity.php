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
    'token_minutes' => (int) env('IDENTITY_TOKEN_MINUTES', 10),
    'refresh_days' => (int) env('IDENTITY_REFRESH_DAYS', 1),

    /* Yayıncı adı: doğrulayıcı uygulamalarda ve onay ekranında görünür. */
    'issuer' => env('IDENTITY_ISSUER', 'Kimlik Yönetim Merkezi'),
];
