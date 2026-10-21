<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\OauthIstemci;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\OrtakCikis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bağlı uygulama: "bu kişi bende çıkış yaptı" (bkz. OrtakCikis).
 *
 * İstemci kimliği ve sırrıyla doğrulanır, kimlik-durumu gibi. Kişi bu
 * uygulamanın kapsamında değilse yok sayılır: bir uygulama yalnız kendi
 * kullanıcısını çıkarabilir. Yanıt her durumda aynı biçimdedir; uç kimin
 * var olduğunu söylemez.
 */
class OturumKapandiController extends Controller
{
    public function __invoke(Request $request, OrtakCikis $cikis, ActivityRecorder $recorder): JsonResponse
    {
        $veri = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'uuid'],
        ]);

        $istemci = OauthIstemci::dogrula($veri['client_id'], $veri['client_secret']);

        if ($istemci === null) {
            return response()->json(['hata' => 'İstemci doğrulanamadı.'], 401);
        }

        $kullanici = User::query()->where('uuid', $veri['subject'])->first();

        if ($kullanici !== null && $istemci->kapsamdaMi($kullanici)) {
            $cikis->kapat($kullanici);
            $recorder->record('cikis', $kullanici->email.' '.$istemci->name.' üzerinden çıkış yaptı · bütün oturumlar kapandı', $kullanici);
        }

        return response()->json(['sonuc' => 'alındı']);
    }
}
