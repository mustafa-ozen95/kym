<?php

declare(strict_types=1);

/*
 * Denetim zincirinin imza anahtarı — APP_KEY'DEN AYRI.
 *
 * Zincir satırları ve uç çapası bu anahtarla imzalanır. APP_KEY'den AYRIDIR:
 * APP_KEY değişince geçmişteki BÜTÜN satırlar doğrulanamaz olurdu; APP_KEY'in
 * döndürülmesi gerektiğinde (ör. sızma şüphesi) zincir bozulmamalı.
 *
 * Boşsa APP_KEY kullanılır: bugüne kadarki kurulumlar aynen çalışır. Rotasyonda
 * eski APP_KEY'in değeri buraya yazılır ve zincir kesintisiz doğrulanır.
 */
return [
    'zincir_anahtari' => env('DENETIM_ZINCIR_ANAHTARI') ?: env('APP_KEY'),
];
