<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Doğrulama postası.
 *
 * Laravel'in kendi `VerifyEmail`'i devralınır: imzalı bağlantıyı, süresini ve
 * doğrulama ucunu o üretir — yeniden yazmak, imzayı yeniden yazmak olurdu.
 * Değişen yalnızca DİL ve KUYRUK.
 */
class EmailVerificationNotification extends VerifyEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * İmzalı doğrulama adresi.
     *
     * Laravel'in kendi üretimi `getKey()` kullanıyor ve bu, SIRALI BİRİNCİL
     * ANAHTARI adres çubuğuna yazardı: kaç kullanıcı olduğunu ve bu hesabın
     * kaçıncı sırada açıldığını söyleyen bir sayı. Bu panelde sıralı anahtar
     * dışarı çıkmaz; her yerde uuid taşınır.
     *
     * İmza ve süre Laravel'in: yalnızca kimliğin biçimi değişiyor.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getRouteKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(config('app.name').' — e-posta adresinizi doğrulayın')
            ->line('Hesabınızı kullanabilmek için adresinizi doğrulamanız gerekiyor.')
            ->action('Adresimi doğrula', $url)
            ->line('Bu bağlantı '.config('auth.verification.expire', 60).' dakika geçerlidir.')
            ->line('Böyle bir hesap açmadıysanız bu iletiyi yok sayabilirsiniz.');
    }
}
