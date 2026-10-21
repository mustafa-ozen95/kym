<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\EmailVerification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Giriş isteği.
 *
 * Doğrulama ve deneme sınırı BURADA durur; controller yalnızca sonucu
 * yönetir. Hesabın durumu parola doğruluğundan AYRI sorulur: parolası doğru
 * ama onaylanmamış bir hesap içeri girmemeli, üstelik bunu ona açıkça
 * söylemeliyiz — yoksa kullanıcı parolasını yanlış sanır.
 */
class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->sinirlaVeSay();

        /*
         * HATIRLAMA ÇEREZİ VERİLMEZ (ikinci parametre false).
         *
         * Kalıcı çerez, hareketsizlik süresini anlamsız kılıyordu: oturum
         * düşse bile kullanıcı sessizce geri içeri alınıyordu. Giriş
         * ekranındaki kutucuk da kaldırıldı — ama karar BURADA duruyor:
         * forma güvenmek denetim değildir, isteği elle kuran "remember=1"
         * yazabilirdi.
         */
        if (! Auth::attempt($this->only('email', 'password'), remember: false)) {
            RateLimiter::hit($this->anahtar());

            throw ValidationException::withMessages([
                'email' => 'E-posta veya parola hatalı.',
            ]);
        }

        $kullanici = $this->user();

        if ($kullanici instanceof User && ! $kullanici->girebilir()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => $kullanici->durum->label().'. Yönetici hesabınızı etkinleştirdiğinde girebilirsiniz.',
            ]);
        }

        /*
         * DOĞRULAMA ZORUNLUYSA ADRES KANITLANMIŞ OLMALI.
         *
         * Kural burada değil `EmailVerification`da duruyor: aynı bilgiyi yazan
         * iki yol var (yöneticinin onayı, kullanıcının tıkladığı bağlantı) ve
         * ikisi de oradan geçmeli. Kopyalansaydı yollardan biri sessizce eski
         * davranışta kalırdı.
         */
        if ($kullanici instanceof User && ! app(EmailVerification::class)->girebilirMi($kullanici)) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'E-posta adresiniz doğrulanmamış. Size gönderilen bağlantıya tıklayın.',
            ]);
        }

        RateLimiter::clear($this->anahtar());
    }

    private function sinirlaVeSay(): void
    {
        /*
         * AYAR `limits.giris` DEĞİL.
         *
         * İkisi aynı şeyi saymıyor: o rotadaki DAKİKADAKİ İSTEK sayısı, bu art
         * arda YANLIŞ PAROLA sayısı. Tek anahtara bağlı olsalar sınırı ayardan
         * kapatmanın yolu olmazdı: `tooManyAttempts($k, 0)` sayaç sıfıra eşit
         * olduğu anda dolu saydığı için sıfır, İLK yanlış paroladan sonra
         * kilitlerdi.
         *
         * Ad bağlı panellerdekiyle aynı (auth.max_attempts); aynı kavramın
         * panelden panele ayrı adları olmasın.
         */
        $sinir = (int) config('auth.max_attempts');

        if ($sinir <= 0) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->anahtar(), $sinir)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => 'Çok fazla deneme yapıldı. '.RateLimiter::availableIn($this->anahtar()).' saniye sonra tekrar deneyin.',
        ]);
    }

    /** Sınır e-posta VE adres başına; tek bir adresten sırayla hesap denemek de sayılır. */
    private function anahtar(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
