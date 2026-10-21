<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kimliklerin ŞU ANKİ durumu — bağlı projeler için arka kanal.
 *
 * NEDEN VAR: merkezde birinin rolü değişince, hesabı askıya alınınca ya da
 * silinince bağlı projelerdeki kopyası eskir. O kişi merkeze giremediği
 * hâlde projede erişmeye devam ederdi; oturumu zaten açıktır ve kimse ona
 * "artık olmaz" demez. Projeler buraya sorar ve kendi hesabını kapatır.
 *
 * KULLANICI OTURUMU YOK: çağıran bir tarayıcı değil, projenin sunucusu.
 * Kimlik doğrulaması istemci kimliği ve SIRRI ile yapılır — yani yalnızca
 * merkeze kayıtlı bir uygulama sorabilir. Sır bcrypt özetiyle saklandığı için
 * karşılaştırma Hash::check ile yapılır.
 *
 * YALNIZCA SORULANI SÖYLER: istemci hangi uuid'leri sorduysa onların durumu
 * döner. Bütün kullanıcıları listeleyen bir uç olsaydı, bağlı tek bir
 * uygulamanın sırrını ele geçiren biri merkezin tüm kadrosunu okurdu.
 *
 * Yetkilendirme: istemci kimliği + sırrı (bkz. OauthClient::dogrula). Oturum aranmaz.
 */
class IdentityStatusController extends Controller
{
    /** Tek istekte sorulabilecek kimlik sayısı. */
    private const SINIR = 500;

    public function __invoke(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:255'],
            'kimlikler' => ['required', 'array', 'min:1', 'max:'.self::SINIR],
            'kimlikler.*' => ['string', 'uuid'],
        ]);

        $istemci = OauthClient::dogrula($veri['client_id'], $veri['client_secret']);

        if ($istemci === null) {
            return response()->json(['hata' => 'İstemci doğrulanamadı.'], 401);
        }

        $kullanicilar = User::with('rol')
            ->whereIn('uuid', $veri['kimlikler'])
            ->get()
            ->keyBy('uuid');

        $yanit = [];

        foreach ($veri['kimlikler'] as $uuid) {
            $kullanici = $kullanicilar->get($uuid);

            /*
             * SİLİNEN KİMLİK "YOK" DÖNER, sessizce atlanmaz.
             *
             * Atlansaydı proje "sormadım mı, yanıt mı gelmedi" ayrımını
             * yapamaz ve silinmiş bir hesabı açık bırakırdı.
             */
            /*
             * "GİREBİLİR" SORAN UYGULAMAYA GÖREDİR.
             *
             * Hesabın merkezdeki durumu açık olsa bile rolü BU UYGULAMANIN
             * listesinde değilse giremez (bkz. OauthClient::erisebilirMi).
             * Tek bir "etkin mi" yanıtı dönseydi, bir uygulamaya girmemesi
             * gereken rol orada da açık kalırdı — kapıyı merkez tutuyor.
             */
            /*
             * E-POSTA VE ROL YALNIZCA KAPSAMDAKİ KİŞİ İÇİN.
             *
             * Bu istemcinin rol listesinin dışındaki biri hakkında "girebilir
             * değil" demek yeter; e-postasını ve rolünü söylemek, uuid'i
             * bilen (bildirimle ya da başka yoldan öğrenen) her bağlı
             * uygulamaya merkezin kadrosunu okutmak olurdu. Liste boşsa
             * (varsayılan) herkes kapsamdadır; durum askıda olsa da kapsam
             * değişmez: askıya alınan kişiyi proje yine tanıyabilmeli.
             */
            $yanit[$uuid] = $kullanici === null
                ? ['durum' => 'yok', 'rol' => null]
                : [
                    'durum' => $kullanici->durum->value,
                    'girebilir' => $istemci->erisebilirMi($kullanici),
                    /* Ortak çıkış: bildirim kaybolursa paneller bu anı buradan öğrenir (bkz. SharedLogout). */
                    'son_cikis' => $kullanici->son_cikis_ms,
                    ...($istemci->kapsamdaMi($kullanici)
                        ? ['rol' => $kullanici->rol?->slug, 'eposta' => $kullanici->email]
                        : ['rol' => null]),
                ];
        }

        return response()->json(['kimlikler' => $yanit]);
    }
}
