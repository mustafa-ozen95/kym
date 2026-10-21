<?php

use App\Http\Middleware\EtkinHesapZorunlu;
use App\Http\Middleware\IkiAdimZorunlu;
use App\Http\Middleware\OrtakCikisSonrasiKapat;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Tema;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * TEMA ÇEREZİ ŞİFRELENMEZ.
         *
         * Onu tarayıcıdaki betik yazıyor ve okuyor; şifreli bir değeri
         * çözemezdi. Sunucu da aynı çerezi okuyup `<html>` etiketini doğru
         * temayla basıyor (bkz. App\Support\Tema) — böylece `<head>` içinde
         * satır içi bir betiğe gerek kalmıyor ve içerik CSP'si eklenebiliyor.
         *
         * Taşıdığı bilgi bir sırra değil bir tercihe ait.
         */
        $middleware->encryptCookies(except: [Tema::COOKIE]);

        /*
         * TERS VEKİLE GÜVEN — konteyner kurulumunda ŞART.
         *
         * TLS önde duran vekilde (Caddy) sonlanıyor ve konteynere düz HTTP
         * geliyor. Bu satır olmadan uygulama isteği "http" sanar: ürettiği
         * bütün mutlak adresler http olur, Secure çerez gönderilmez ve
         * merkeze giden OAuth dönüş adresi kayıtlı adresle eşleşmediği için
         * takas "redirect_uri mismatch" ile reddedilir.
         *
         * Liste '*' DEĞİL: X-Forwarded-For'a güvenmek onu YAZILABİLİR kılar.
         * Hız sınırları ve denetim kayıtları istemci adresine bakıyor; herkese
         * güvenilseydi kendi adresini yazan biri ikisini de atlatırdı.
         * Varsayılan özel ağlardır (docker köprüsü 172.16/12 içinde kalır);
         * başka bir yerleşimde TRUSTED_PROXIES ile daraltılır.
         */
        $middleware->trustProxies(
            at: array_filter(explode(',', (string) env(
                'TRUSTED_PROXIES',
                '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
            ))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        /*
         * İkinci adım zorunluluğu web isteklerinin tamamında sorulur:
         * kullanıcı anahtarı açıkken kural yalnızca yönetimi değil, oturum
         * açmış her isteği kapsıyor. Kuralın kendisi ayara bakar; kapalıyken
         * hiçbir şey yapmaz.
         */
        /*
         * Askıya alınan hesabın oturumu anında kapanır: durum yalnızca
         * girişte sorulsaydı hesabı askıya almak açık oturumu etkilemezdi.
         *
         * SIRA ÖNEMLİ: önce hesap etkin mi, sonra ikinci adım. Tersi olsaydı
         * askıya alınmış birinden önce ikinci adım istenirdi.
         */
        $middleware->web(append: [OrtakCikisSonrasiKapat::class, EtkinHesapZorunlu::class, IkiAdimZorunlu::class]);

        /*
         * Panelin sayfaları ÇERÇEVEYE ALINAMAZ.
         *
         * Güvenlik başlıkları olmadan herhangi bir site paneli görünmez bir
         * çerçeveye alıp kullanıcının tıklamasını paneldeki bir düğmeye
         * yönlendirebilirdi.
         */
        /*
         * GENEL yığına eklenir, "web" grubuna DEĞİL.
         *
         * Gruba eklendiğinde en içte kalıyor: kimlik doğrulaması düştüğünde
         * fırlatılan istisna oradan yukarı geçip yanıtı istisna işleyicisi
         * kuruyor ve o yanıt bir daha ara katmandan geçmiyor — giriş
         * yönlendirmesi ve hata sayfaları başlıksız kalıyordu (ör. bir
         * yönlendirmenin 302'si başlıksız çıkıyordu).
         */
        $middleware->append(SecurityHeaders::class);

        /*
         * Oturumsuz kullanıcı, GİRİŞ PENCERESİ AÇIK ana sayfaya gider.
         *
         * Laravel varsayılan olarak 'login' adlı bir rotaya yönlendiriyor;
         * bu uygulamada öyle bir sayfa yok (giriş pencereden yapılıyor) ve
         * yönlendirme 500 üretiyordu.
         */
        $middleware->redirectGuestsTo(function ($request) {
            /*
             * Tarayıcı giriş penceresine gönderilir; JETONLA konuşan istemci
             * ise yönlendirilmez, 401 alır. Yönlendirme dönseydi istemci
             * "başarılı" sayılan bir HTML sayfası okuyup jetonun geçersiz
             * olduğunu anlamazdı.
             */
            /*
             * /oauth/authorize BURAYA GİRMEZ: o bir TARAYICI akışıdır ve
             * kullanıcının giriş penceresine gitmesi gerekir. Yalnızca
             * jetonla konuşulan uçlar 401 alır.
             */
            if ($request->expectsJson() || $request->is('oauth/kullanici', 'api/*')) {
                return null;
            }

            return route('hizli-erisim', ['pencere' => 'giris']);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
