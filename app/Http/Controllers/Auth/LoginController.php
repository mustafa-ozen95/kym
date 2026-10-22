<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\SharedLogout;
use App\Services\TwoStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Giriş ve çıkış.
 *
 * Yetkilendirme: giriş ekranı doğal olarak oturumsuzlara açıktır; çıkış ise
 * yalnızca oturum sahibini ilgilendirir ve rotası `auth` altındadır.
 */
class LoginController extends Controller
{
    /** Oturumda bekleyen kullanıcının anahtarı: parola geçti, ikinci adım kaldı. */
    public const PENDING = 'iki_adim_bekleyen';

    public function store(LoginRequest $request, ActivityRecorder $recorder): RedirectResponse
    {
        $request->authenticate();

        $kullanici = $request->user();

        /*
         * İKİNCİ ADIM VARSA OTURUM HENÜZ AÇILMAZ.
         *
         * Parolanın doğru olması yetmiyor; kullanıcı burada oturumdan
         * düşürülüp yalnızca kimliği oturumda tutuluyor. Aksi hâlde parolayı
         * ele geçiren biri, kod sorulan ekranı görmezden gelip doğrudan bir
         * adrese giderek içeri girebilirdi.
         */
        if ($kullanici->ikiAdimliMi()) {
            Auth::guard('web')->logout();

            $request->session()->put(self::PENDING, $kullanici->getKey());

            return redirect()->route('quick-access', ['window' => 'verify']);
        }

        return $this->tamamla($request, $kullanici, $recorder);
    }

    /**
     * İkinci adım kodu.
     *
     * Bu uç oturum AÇMADAN çağrılır; hangi kullanıcı olduğunu oturumda
     * bekleyen kimlik söyler.
     */
    public function verify(Request $request, TwoStep $ikiAdim, ActivityRecorder $recorder): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);

        $kullanici = User::find($request->session()->get(self::PENDING));

        if (! $kullanici) {
            return redirect()->route('quick-access', ['window' => 'login'])
                ->withErrors(['code' => 'Doğrulama süresi doldu; baştan giriş yapın.']);
        }

        if (! $this->kodSinirlaVeSay($request, $kullanici)) {
            return back()->withErrors(['code' => 'Çok fazla deneme yapıldı. Biraz sonra tekrar deneyin.']);
        }

        if (! $ikiAdim->dogrula($kullanici, (string) $request->string('code'))) {
            $recorder->record('iki-adim-hata', $kullanici->email.' için kod doğrulanamadı', $kullanici);

            return back()->withErrors(['code' => 'Kod doğrulanamadı.']);
        }

        RateLimiter::clear($this->kodAnahtari($kullanici));
        /* Hatırlama çerezi yok: hareketsizlik süresi gerçekten süre olsun. */
        Auth::guard('web')->login($kullanici, remember: false);
        $request->session()->forget(self::PENDING);

        return $this->tamamla($request, $kullanici, $recorder);
    }

    private function tamamla(Request $request, User $kullanici, ActivityRecorder $recorder): RedirectResponse
    {
        $request->session()->regenerate();
        $kullanici->forceFill(['son_giris_at' => now()])->save();

        $recorder->record('giris', $kullanici->email.' giriş yaptı', $kullanici);

        return redirect()->intended(route('quick-access'));
    }

    /**
     * Kod denemesi de sınırlanır.
     *
     * Altı hanelik bir kod, sınırsız deneme verilirse dakikalar içinde
     * bulunur; ikinci adımın anlamı kalmazdı.
     */
    private function kodSinirlaVeSay(Request $request, User $kullanici): bool
    {
        $anahtar = $this->kodAnahtari($kullanici);

        if (RateLimiter::tooManyAttempts($anahtar, (int) config('limits.two_step'))) {
            return false;
        }

        RateLimiter::hit($anahtar, 300);

        return true;
    }

    private function kodAnahtari(User $kullanici): string
    {
        return 'iki-adim:'.$kullanici->getKey();
    }

    public function destroy(Request $request, ActivityRecorder $recorder, SharedLogout $cikis): RedirectResponse
    {
        $recorder->record('cikis', ($request->user()?->email ?? '').(SharedLogout::zamanAsimiMi($request) ? ' oturum süresi doldu' : ' çıkış yaptı · bütün oturumlar kapandı'), $request->user());

        /*
         * Merkezden çıkış bağlı panellerdeki oturumları da kapatır (bkz.
         * SharedLogout). Ekran kilidinin çıkışı (süre doldu) ortak değildir.
         */
        if ($request->user() !== null && ! SharedLogout::zamanAsimiMi($request)) {
            $cikis->kapat($request->user());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('quick-access');
    }
}
