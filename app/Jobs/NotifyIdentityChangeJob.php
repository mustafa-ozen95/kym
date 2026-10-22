<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesClients;
use App\Models\OauthClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Bu kimlik değişti" — bağlı bütün projelere.
 *
 * NEDEN VAR: projeler merkezi beş dakikada bir yokluyordu. Erişimi kaldırılan
 * biri o beş dakika boyunca projede çalışmaya devam edebiliyordu. Bildirim,
 * bekleme süresini saniyelere indirir.
 *
 * BİLDİRİM VERİ TAŞIMAZ. Yalnızca hangi kimliğin değiştiğini söyler; yeni rol
 * ya da durum gönderilmez. Proje doğruyu merkezden KENDİ sorar. Böylece
 * bildirim yolu ele geçse bile kimsenin rolü yükseltilemez.
 *
 * ÇEKME TURU YEDEKTE KALIR: bildirim kaybolabilir (ağ, yeniden başlatma,
 * proje o an kapalı). Kaybolan bildirimin bedeli gecikmedir, kalıcı ayrışma
 * değil.
 *
 * BAŞARISIZLIK SESSİZ DEĞİL ama TURU DÜŞÜRMEZ: bir proje yanıt vermiyorsa
 * diğerlerine haber verilmeye devam edilir ve durum kayda yazılır.
 */
class NotifyIdentityChangeJob implements ShouldQueue
{
    use NotifiesClients;
    use Queueable;

    public int $tries = 1;

    /**
     * @param  bool  $kapsamli  Eski/yeni rol verildi mi? Kuyrukta bekleyen eski
     *                          biçimli işler (rol bilgisiz) HERKESE gitmeye devam eder.
     */
    public function __construct(
        public string $subject,
        public ?int $eskiRol = null,
        public ?int $yeniRol = null,
        public bool $kapsamli = false,
    ) {}

    public function handle(): void
    {
        /*
         * KİŞİ BU UYGULAMANIN KAPSAMINDA DEĞİLSE haber gitmez.
         *
         * Bildirim uuid taşır; rol listesi dışındaki bir uygulamaya
         * göndermek, ona merkezin kadrosundan bir kimlik sızdırır.
         * Kişi eski ya da yeni rolüyle kapsamdaysa gider: rolü listeden
         * ÇIKARILAN kişiyi proje de bilmeli ki hesabını kapatsın.
         *
         * Sırrı tanımlanmamış istemciye bildirim gitmez; o proje çekme
         * turuyla öğrenir (bkz. NotifiesClients).
         */
        $this->istemcilereBildir(
            '/api/identity/refresh',
            ['subject' => $this->subject],
            $this->subject,
            fn (OauthClient $istemci): bool => ! $this->kapsamli
                || $istemci->rolKapsamdaMi($this->eskiRol)
                || $istemci->rolKapsamdaMi($this->yeniRol),
        );
    }
}
