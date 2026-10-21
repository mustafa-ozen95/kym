<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Services\ActivityRecorder;
use App\Services\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * E-posta doğrulama.
 *
 * Yetkilendirme: bağlantı OTURUMSUZ açılır ve bu bilinçli. Laravel'in hazır
 * `EmailVerificationRequest`'i oturum ister; o akışta kullanıcı kayıttan
 * hemen sonra içeri alınır. Burada alınmıyor — hesap yönetici onayı bekliyor
 * ve doğrulanmamış kullanıcı zaten giriş yapamıyor. Oturum şartı konsaydı
 * kullanıcı, doğrulamak için giriş yapmak, giriş yapmak için doğrulamak
 * zorunda kalırdı.
 *
 * Bağlantıyı koruyan şey oturum değil İMZA: adres kurcalanırsa `signed` ara
 * katmanı 403 döner, süresi dolmuşsa da geçmez. Ayrıca `hash` alanı adresin
 * özetini taşır; kullanıcı e-postasını değiştirdiğinde eski bağlantı ölür.
 */
class EmailVerificationController extends Controller
{
    public function dogrula(Request $request, string $id, string $hash, ActivityRecorder $recorder): RedirectResponse
    {
        /* Adres uuid taşır (bkz. EmailVerificationNotification::verificationUrl). */
        $kullanici = User::firstWhere('uuid', $id);

        if (! $kullanici || ! hash_equals(sha1($kullanici->getEmailForVerification()), $hash)) {
            return redirect()->route('hizli-erisim')
                ->with('error', 'Doğrulama bağlantısı geçersiz. Yeni bir bağlantı isteyin.');
        }

        if (! $kullanici->hasVerifiedEmail()) {
            $kullanici->markEmailAsVerified();
            event(new Verified($kullanici));

            $recorder->record('eposta-dogrulandi', $kullanici->email.' adresini doğruladı', $kullanici);
        }

        return redirect()->route('hizli-erisim', ['pencere' => 'giris'])
            ->with('status', 'E-posta adresiniz doğrulandı.');
    }

    /**
     * Bağlantıyı yeniden gönderir.
     *
     * Yanıt, hesap OLSA DA OLMASA DA aynıdır: farklı yanıt verilseydi bu uç,
     * kimin kayıtlı olduğunu sorgulamanın yoluna dönerdi. Sınır da bu yüzden
     * var — hem sorgulamayı hem posta kutusu doldurmayı pahalı kılar.
     */
    public function yenidenGonder(Request $request, EmailVerification $dogrulama): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $yanit = redirect()->route('hizli-erisim')
            ->with('status', 'Adres kayıtlıysa doğrulama bağlantısı gönderildi.');

        if (! $dogrulama->zorunluMu()) {
            return $yanit;
        }

        $anahtar = 'eposta-dogrulama:'.$request->ip();

        if (RateLimiter::tooManyAttempts($anahtar, (int) config('limits.dogrulama_postasi'))) {
            return $yanit;
        }

        RateLimiter::hit($anahtar, 600);

        $kullanici = User::where('email', $request->string('email'))->first();

        if ($kullanici && ! $kullanici->hasVerifiedEmail()) {
            $kullanici->notify(new EmailVerificationNotification);
        }

        return $yanit;
    }
}
