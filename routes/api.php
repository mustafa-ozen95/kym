<?php

declare(strict_types=1);

use App\Http\Controllers\Oauth\KimlikDurumuController;
use App\Http\Controllers\Oauth\OturumKapandiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Arka kanal
|--------------------------------------------------------------------------
| Buradaki uç KULLANICI OTURUMUYLA DEĞİL, İSTEMCİ KİMLİĞİYLE konuşur: çağıran
| bir tarayıcı değil, bağlı projenin sunucusudur. Bu yüzden `auth` altında
| değil; kimlik doğrulaması istemci kimliği ve sırrı ile, ucun kendi içinde
| yapılır (bkz. KimlikDurumuController).
|
| NEDEN VAR: merkezde birinin rolü değişince ya da hesabı askıya alınınca,
| bağlı projelerdeki kopyası eskiyor ve o kişi orada erişmeye devam ediyordu.
| Projeler bu uçtan "bu kimlikler şu an ne durumda" diye sorar ve kendi
| hesaplarını buna göre kapatır.
|
| Sıralı birincil anahtar geçmez: kimlikler uuid ile sorulur.
*/

Route::post('kimlik-durumu', KimlikDurumuController::class)
    ->middleware('throttle:120,1')
    ->name('api.kimlik-durumu');

/* Ortak çıkış: bağlı uygulama "bu kişi bende çıktı" der (bkz. App\Services\OrtakCikis). */
Route::post('oturum-kapandi', OturumKapandiController::class)
    ->middleware('throttle:60,1')
    ->name('api.oturum-kapandi');
