<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hızlı Erişim kutucuklarının seçenekleri
|--------------------------------------------------------------------------
|
| Salonlar ve şeritler ÇİZİM TARAFINDA tanımlıdır (resources/js): salon
| başlıkları sayfanın kendi düzeni, şeritler ise elle çizilmiş SVG
| animasyonlardır. Buradaki liste, ekleme ekranının hangi seçenekleri
| sunacağını söyler — yeni bir şerit çizilirse buraya bir satır eklenir.
|
| Adlar sayfadaki başlıklarla birebir aynı olmalı; ekranda başka türlü
| görünmesi kullanıcıyı yanıltırdı.
|
*/

return [
    'gruplar' => [
        'ic' => 'İç sistemler',
        'mon' => 'Monitör',
        'cam' => 'Kamera ve sensör',
        'dis' => 'Dış sistemler',
    ],

    /* Şerit = kartın altındaki animasyon. Hangi işi anlattığına göre seçilir. */
    'lanes' => [
        's1' => 'Eşitleyici çubukları',
        's2' => 'Akan dalga',
        's3' => 'Yazılan satırlar',
        's4' => 'Açılan alanlar',
        's5' => 'Paket ve duraklar',
        's6' => 'Soru ve yanıt',
        's7' => 'Tarayan pencere',
        's8' => 'Kalp atışı (EKG)',
        's9' => 'Kayan bant',
        's10' => 'Yayılan ping halkaları',
        's11' => 'Kamera kareleri',
        's12' => 'Ölçek ve gösterge',
        's13' => 'Büyüyen sütunlar',
    ],
];
