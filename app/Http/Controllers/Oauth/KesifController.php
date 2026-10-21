<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Keşif belgesi.
 *
 * İstemciyi kuran kişi uçları elle aramasın diye. Kimlik jetonu (id_token)
 * ÜRETMİYORUZ: istemciler kimliği userinfo ucundan okuyor, dolayısıyla
 * doğrulanacak bir imza ve yayınlanacak bir anahtar kümesi (JWKS) yok.
 * Bunu belgede açıkça söylemek, eksik sanılıp aranmasından iyidir.
 *
 * Yetkilendirme: bu belge herkese açıktır — içinde sır yok, yalnızca adresler
 * var ve istemcinin onu kurulumdan önce okuyabilmesi gerekir.
 */
class KesifController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'userinfo_endpoint' => url('/oauth/kullanici'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],

            /*
             * Hangi alanların döneceği de belgede yazar.
             *
             * Başka dillerdeki istemciler (Node, Python) kendi OIDC
             * kütüphaneleriyle bağlanıyor ve userinfo'nun ne döndürdüğünü
             * kurulum öncesi bilmeleri gerekiyor; yoksa deneyerek buluyorlar.
             */
            'scopes_supported' => [],
            'claims_supported' => ['sub', 'name', 'email', 'email_verified'],

            /*
             * Kimlik jetonu (id_token) ÜRETİLMEZ: kimlik userinfo ucundan
             * okunur, dolayısıyla doğrulanacak imza ve JWKS yoktur. Eksik
             * sanılıp aranmasın diye belgede açıkça söyleniyor.
             */
            'id_token_signing_alg_values_supported' => [],
        ]);
    }
}
