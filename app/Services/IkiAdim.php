<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * İkinci adımın kurulması, doğrulanması ve kapatılması.
 *
 * Kural tek yerde durur: giriş ekranı, kurulum ekranı ve kurtarma yolu aynı
 * mantığı kullanır. İki yere kopyalansaydı biri değişince diğeri sessizce
 * eski kalırdı — ve bu, ikinci adımın atlanabildiği bir kapı demek olurdu.
 */
class IkiAdim
{
    public function __construct(private Totp $totp) {}

    /** Kuruluma başlar: anahtar üretilir ama HENÜZ onaylanmaz. */
    public function baslat(User $kullanici): string
    {
        $anahtar = $this->totp->secret();

        /*
         * Onay tarihi boş bırakılır: kullanıcı bir kod girip kurulumun
         * gerçekten çalıştığını göstermeden ikinci adım devreye girmemeli.
         * Yoksa telefonuna ekleyemeden kendini dışarıda bulur.
         */
        $kullanici->forceFill([
            'totp_secret' => $anahtar,
            'totp_confirmed_at' => null,
            'totp_recovery' => null,
        ])->save();

        return $anahtar;
    }

    /**
     * Kurulumu onaylar ve kurtarma kodlarını üretir.
     *
     * @return array<int, string>|null Kodlar YALNIZCA burada, bir kez döner.
     */
    public function onayla(User $kullanici, string $kod): ?array
    {
        if (blank($kullanici->totp_secret) || ! $this->totp->verify((string) $kullanici->totp_secret, $kod)) {
            return null;
        }

        $kodlar = $this->totp->recoveryCodes();

        $kullanici->forceFill([
            'totp_confirmed_at' => now(),
            /* Özetlenerek saklanır: sızıntı hâlinde hazır anahtar olmasınlar. */
            'totp_recovery' => array_map(fn (string $k): string => Hash::make($k), $kodlar),
        ])->save();

        return $kodlar;
    }

    /**
     * Giriş sırasında kod ya da kurtarma kodu doğrulanır.
     *
     * KOD BİR KEZ GEÇER.
     *
     * Bir TOTP kodu 90 saniyeye kadar geçerli kalıyor (30 saniyelik adım artı
     * iki komşu) ve "geçerli mi" sorusunun cevabı o sürede değişmiyordu: aynı
     * kod ikinci kez de kabul ediliyordu. Omzunun üstünden kodu gören ya da
     * araya giren biri için bu, pencerenin sonuna kadar süren ikinci bir
     * giriş hakkıydı — ikinci adımın var olma sebebi tam da bunu
     * engellemekti (RFC 6238 §5.2).
     *
     * Damga ÖNBELLEKTE tutulur, veritabanında değil: bilginin ömrü pencere
     * kadar. `Cache::add` atomiktir, yani aynı kodla aynı anda gelen iki
     * istekten yalnızca biri geçer — okuyup yazsaydık ikisi de geçerdi.
     *
     * Kurtarma kodları zaten tek kullanımlık (bkz. kurtarmaKodunuHarca).
     */
    public function dogrula(User $kullanici, string $kod): bool
    {
        $adim = $this->totp->eslesenAdim((string) $kullanici->totp_secret, $kod);

        if ($adim !== null) {
            return Cache::add(
                "kimlik:totp-harcandi:{$kullanici->getKey()}:{$adim}",
                true,
                $this->totp->pencereSaniyesi(),
            );
        }

        return $this->kurtarmaKodunuHarca($kullanici, $kod);
    }

    /**
     * Kurtarma kodu BİR KEZ kullanılır.
     *
     * Kullanıldıktan sonra listeden düşer; yoksa kod, çalındığında sınırsız
     * bir arka kapıya dönüşürdü.
     */
    private function kurtarmaKodunuHarca(User $kullanici, string $kod): bool
    {
        $kalanlar = $kullanici->totp_recovery ?? [];
        $temiz = strtolower(trim($kod));

        foreach ($kalanlar as $sira => $ozet) {
            if (! Hash::check($temiz, $ozet)) {
                continue;
            }

            unset($kalanlar[$sira]);
            $kullanici->forceFill(['totp_recovery' => array_values($kalanlar)])->save();

            return true;
        }

        return false;
    }

    public function kapat(User $kullanici): void
    {
        $kullanici->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'totp_recovery' => null,
        ])->save();
    }

    /** Kaç kurtarma kodu kaldı? Kullanıcı bunu görmeli. */
    public function kalanKurtarma(User $kullanici): int
    {
        return count($kullanici->totp_recovery ?? []);
    }
}
