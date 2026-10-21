<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\OrtakCikis;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kişinin son çıkışından ÖNCE açılmış merkez oturumu kapanır.
 *
 * Çıkış bağlı bir panelden de gelebilir (bkz. OrtakCikis): panelden çıkan
 * kişi merkezde açık kalırsa panele geri dönünce sorulmadan yeniden girerdi.
 */
class OrtakCikisSonrasiKapat
{
    public function handle(Request $request, Closure $next): Response
    {
        $kullanici = $request->user();

        if ($kullanici === null || ! $request->hasSession() || ! Auth::guard() instanceof StatefulGuard
            || ! OrtakCikis::oturumEskiMi($kullanici, $request->session())) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('hizli-erisim', ['pencere' => 'giris'])
            ->withErrors(['email' => 'Bağlı bir uygulamadan çıkış yapıldığı için oturumunuz kapatıldı.']);
    }
}
