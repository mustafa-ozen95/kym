<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\NotifySharedLogoutJob;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * ORTAK ÇIKIŞ — merkez ve bağlı paneller tek oturum gibi kapanır.
 *
 * Bir panelden (ör. EYS) çıkış yapan kişi merkezden de çıkmış olmalı.
 * Her uygulama kendi oturumunu tutar; ortak çerez YOK (kullanıcı
 * numaraları uygulamalar arasında farklı, anahtarlar ayrı). Bunun yerine:
 *
 *   1. Çıkış (merkezde ya da panelden bildirilen) kullanıcının
 *      `son_cikis_ms` anını yazar.
 *   2. Bu andan ÖNCE açılmış her oturum bir sonraki istekte kapanır:
 *      merkezde CloseAfterSharedLogout, panellerde kendi ara katmanları.
 *   3. Paneller imzalı bildirimle hemen haberdar edilir
 *      (NotifySharedLogoutJob); bildirim kaybolursa kimlik-durumu sorgusu
 *      `son_cikis` alanıyla aynı bilgiyi verir.
 *
 * Çıkış kişinin BÜTÜN oturumlarını kapatır (başka cihazdakiler dahil).
 */
final class SharedLogout
{
    /** Oturumun açıldığı an (ms); Login olayında yazılır (bkz. AppServiceProvider). */
    public const OTURUM_ANAHTARI = 'oturum_acilis_ms';

    public static function simdiMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /** Kişinin bütün oturumlarını kapatır ve panellere bildirir; yazılan anı döndürür. */
    public function kapat(User $kullanici): int
    {
        $an = self::simdiMs();

        /* Sorguyla: model olayları (BroadcastsIdentityChange) çıkışı kimlik değişikliği sanmasın. */
        User::query()->whereKey($kullanici->getKey())->update(['son_cikis_ms' => $an]);
        $kullanici->son_cikis_ms = $an;

        NotifySharedLogoutJob::dispatch((string) $kullanici->uuid, $an);

        return $an;
    }

    /**
     * Çıkış ekran kilidinden mi (süre doldu)? O çıkış ortak değildir: bir
     * sekmede süresi dolan kişi bağlı panellerden atılmaz (bkz. session-lock.js).
     */
    public static function zamanAsimiMi(Request $istek): bool
    {
        return $istek->header('X-Oturum-Zaman-Asimi') === '1';
    }

    /** Bu oturum kişinin son çıkışından önce mi açıldı? */
    public static function oturumEskiMi(User $kullanici, Session $oturum): bool
    {
        $cikis = (int) ($kullanici->son_cikis_ms ?? 0);

        return $cikis > 0 && (int) $oturum->get(self::OTURUM_ANAHTARI, 0) < $cikis;
    }
}
