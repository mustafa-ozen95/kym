<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesClients;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Bu kişi şu anda çıkış yaptı" — bağlı her uygulamaya (bkz. SharedLogout).
 *
 * Anı TAŞIR ve imza onu da kapsar (`cikis|<subject>|<an>`): panel merkeze
 * yeniden sormadan uygular. Kimlik bildiriminden ayrı uç, çünkü paneller o
 * ucu kişi başına birkaç saniyede bire kısıyor; çıkış kısılmamalı.
 */
class NotifySharedLogoutJob implements ShouldQueue
{
    use NotifiesClients;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $subject,
        public int $an,
    ) {}

    public function handle(): void
    {
        $kullanici = User::query()->where('uuid', $this->subject)->first();

        /* Kapsam dışı uygulamaya kimlik sızdırılmaz (bkz. NotifyIdentityChangeJob). */
        $this->istemcilereBildir(
            '/api/kimlik/cikis',
            ['subject' => $this->subject, 'an' => $this->an],
            'cikis|'.$this->subject.'|'.$this->an,
            fn (OauthClient $istemci): bool => $istemci->kapsamdaMi($kullanici),
        );
    }
}
