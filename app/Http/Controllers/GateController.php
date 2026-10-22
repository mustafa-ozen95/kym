<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\System;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Kapı — korunan sayfanın bekçisi.
 *
 * NE İŞE YARAR: statik bir sayfa (eski bir PHP yönetim ekranı gibi) kendi
 * parolasını sormak yerine bu uca sorar: "bu tarayıcıdaki hesap şu kutucuğa
 * yetkili mi?". Yanıt 200 ise sayfa açılır, 401/403 ise açılmaz. Böylece o
 * sayfanın kendi kullanıcı listesi, kendi parolası ve kendi oturumu olmaz —
 * hepsi merkezde durur.
 *
 * nginx tarafında:
 *
 *   location / {
 *       auth_request /gate;
 *       error_page 401 = @girise;
 *   }
 *
 *   location = /gate {
 *       internal;
 *       proxy_pass https://kimlik.ornek.test/gate/<anahtar>;
 *       proxy_pass_request_body off;
 *       proxy_set_header Content-Length "";
 *   }
 *
 * Yetkilendirme: `auth` ara katmanının DIŞINDA ve bu bilinçli — o katman
 * kimliksiz isteği giriş sayfasına YÖNLENDİRİR, nginx ise 2xx/401/403 dışında
 * her yanıtı 500 sayar. Kimlik kontrolü burada yapılır ve 401 döner.
 *
 * GÖVDE YOK: yanıt yalnızca durum kodudur. Kimin yetkili olduğu, hangi
 * kutucuğun var olduğu gibi bilgiler bu uçtan sızmamalı — 403 ile 404 ayrımı
 * bile "böyle bir kutucuk var" der, o yüzden bilinmeyen anahtar da 403 döner.
 */
class GateController extends Controller
{
    public function __invoke(Request $request, string $anahtar): Response
    {
        $kullanici = $request->user();

        if ($kullanici === null) {
            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $sistem = System::etkin()->firstWhere('anahtar', $anahtar);

        /*
         * Kural TEK YERDE: sayfadaki liste de bu yöntemden geçiyor. Kopya bir
         * kontrol yazılsaydı, biri değişince liste ile kapı farklı yanıt
         * verirdi — kutucuğu göremeyen sayfayı açabilir ya da tersi olurdu.
         */
        if ($sistem === null || ! $sistem->gorebilirMi($kullanici)) {
            return response('', Response::HTTP_FORBIDDEN);
        }

        /*
         * Korunan sayfa kimin girdiğini bilmek isteyebilir; başlıkla söylenir.
         * Gövde yine boş: nginx yalnızca durum koduna bakıyor.
         */
        return response('', Response::HTTP_OK)
            ->header('X-Kimlik-Kullanici', (string) $kullanici->uuid)
            ->header('X-Kimlik-Eposta', (string) $kullanici->email)
            ->header('X-Kimlik-Ad', (string) $kullanici->name);
    }
}
