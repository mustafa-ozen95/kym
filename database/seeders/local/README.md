# Yerel veri

Bu dizin **depoya girmez** (`.gitignore`). Yazılım depoda durur, veri burada.

## `systems.php`

Hızlı Erişim sayfasındaki kutucuklar. `SystemSeeder` varsa okur, yoksa hiçbir
şey yapmaz — kutucuklar zaten ekrandan da eklenebilir.

```php
<?php

return [
    [
        'ad' => 'CRM',
        'aciklama' => 'Kısa açıklama',
        'adres' => 'https://crm.ornek.test',   // '#' ya da göreli yol da olur
        'kod' => 'CRM-01',                      // BENZERSİZ; güncelleme anahtarı
        'renk' => '#e0a44a',
        'grup' => 'ic',                         // salon anahtarı
        'serit' => 's1',
        'kabin' => 'k1',
        'yan_etiket' => null,                   // ikinci bağlantı (isteğe bağlı)
        'yan_adres' => null,
        'sira' => 10,
    ],
];
```

Çalıştırmak için:

```bash
php artisan db:seed --class=SystemSeeder
```
