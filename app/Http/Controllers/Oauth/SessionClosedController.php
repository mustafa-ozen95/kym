<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\OauthClient;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\SharedLogout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bağlı uygulama: "bu kişi bende çıkış yaptı" (bkz. SharedLogout).
 *
 * İstemci kimliği ve sırrıyla doğrulanır, identity-status gibi. Kişi bu
 * uygulamanın kapsamında değilse yok sayılır: bir uygulama yalnız kendi
 * kullanıcısını çıkarabilir. Yanıt her durumda aynı biçimdedir; uç kimin
 * var olduğunu söylemez.
 */
class SessionClosedController extends Controller
{
    public function __invoke(Request $request, SharedLogout $cikis, ActivityRecorder $recorder): JsonResponse
    {
        $veri = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'uuid'],
        ]);

        $istemci = OauthClient::dogrula($veri['client_id'], $veri['client_secret']);

        if ($istemci === null) {
            return response()->json(['error' => 'İstemci doğrulanamadı.'], 401);
        }

        $kullanici = User::query()->where('uuid', $veri['subject'])->first();

        if ($kullanici !== null && $istemci->kapsamdaMi($kullanici)) {
            $cikis->kapat($kullanici);
            $recorder->record('cikis', $kullanici->email.' '.$istemci->name.' üzerinden çıkış yaptı · bütün oturumlar kapandı', $kullanici);
        }

        return response()->json(['result' => 'received']);
    }
}
