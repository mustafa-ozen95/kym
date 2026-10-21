<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Askıya alınan hesabın oturumu ANINDA kapanır.
 *
 * NEDEN VAR: durum yalnızca GİRİŞTE sorulyordu (bkz. LoginRequest). Bir hesabı
 * askıya almak, o kişinin AÇIK oturumunu etkilemiyordu: yönetici "askıya
 * aldım" diyor, kişi sekmesi açık olduğu sürece tam yetkiyle çalışmaya devam
 * ediyordu. Burası kimliğin kaynağı — bağlı projeler bu merkeze bakıyor —
 * yani en son burada olması gereken bir açıktı.
 *
 * Kural her web isteğini kapsar; jetonla konuşan uçlar kendi kapılarından
 * geçer (`auth:api`).
 */
class ActiveAccountRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $kullanici = $request->user();

        if ($kullanici !== null && ! $kullanici->girebilir()) {
            /*
             * JETONLA GELEN İSTEK (oauth/kullanici): bakan guard oturum
             * tutmuyor, çıkış yapılacak bir oturum yok; logout() burada 500
             * üretirdi. Açık ve sebebi kodlu bir ret döner. (Jeton hesap askıya alınınca zaten iptal edilir;
             * bu, iptalden kaçan bir jetona karşı ikinci savunma.)
             */
            if (! Auth::guard() instanceof StatefulGuard) {
                return response()->json([
                    'kod' => 'hesap_etkin_degil',
                    'hata' => 'Bu hesapla erişim şu an kapalı.',
                ], 403);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('hizli-erisim', ['pencere' => 'giris'])
                ->withErrors(['email' => $kullanici->durum->label().'. Yöneticinizle görüşün.']);
        }

        return $next($request);
    }
}
