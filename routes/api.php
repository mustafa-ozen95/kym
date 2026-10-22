<?php

declare(strict_types=1);

use App\Http\Controllers\Oauth\IdentityStatusController;
use App\Http\Controllers\Oauth\SessionClosedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Arka kanal
|--------------------------------------------------------------------------
| Buradaki uç KULLANICI OTURUMUYLA DEĞİL, İSTEMCİ KİMLİĞİYLE konuşur: çağıran
| bir tarayıcı değil, bağlı projenin sunucusudur. Bu yüzden `auth` altında
| değil; kimlik doğrulaması istemci kimliği ve sırrı ile, ucun kendi içinde
| yapılır (bkz. IdentityStatusController).
|
| NEDEN VAR: merkezde birinin rolü değişince ya da hesabı askıya alınınca,
| bağlı projelerdeki kopyası eskiyor ve o kişi orada erişmeye devam ediyordu.
| Projeler bu uçtan "bu kimlikler şu an ne durumda" diye sorar ve kendi
| hesaplarını buna göre kapatır.
|
| Sıralı birincil anahtar geçmez: kimlikler uuid ile sorulur.
*/

Route::post('identity-status', IdentityStatusController::class)
    ->middleware('throttle:120,1')
    ->name('api.identity-status');

/* Ortak çıkış: bağlı uygulama "bu kişi bende çıktı" der (bkz. App\Services\SharedLogout). */
Route::post('session-closed', SessionClosedController::class)
    ->middleware('throttle:60,1')
    ->name('api.session-closed');
