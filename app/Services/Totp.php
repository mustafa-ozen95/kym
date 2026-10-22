<?php

declare(strict_types=1);

namespace App\Services;

use Random\RandomException;

/**
 * Zaman tabanlı tek kullanımlık parola (TOTP, RFC 6238).
 *
 * NEDEN PAKET DEĞİL: algoritma kısa ve kesin tanımlı — HMAC-SHA1'in son dört
 * biti bir kaydırma verir, oradan altı hane okunur. Daha önemlisi, doğruluğu
 * RFC'nin KENDİ test vektörleriyle kanıtlanabiliyor (bkz. TotpTest); yani
 * "çalışıyor gibi görünüyor" demek zorunda kalmıyoruz. Kendi kriptomuzu
 * icat etmiyoruz, standart bir hesabı uyguluyoruz.
 *
 * PENCERE: doğrulama, bulunduğumuz adımın bir öncesini ve bir sonrasını da
 * kabul eder. Telefon saati birkaç saniye kayıksa kod reddedilir ve kullanıcı
 * kendini kilitli bulur; ±1 adım bunu çözer, kabul aralığını 90 saniyede
 * tutar.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const STEP = 30;

    private const DIGITS = 6;

    /** Kabul edilen komşu adım sayısı (öncesi ve sonrası). */
    private const WINDOW = 1;

    /**
     * Yeni bir gizli anahtar (base32, 160 bit).
     *
     * @throws RandomException
     */
    public function secret(): string
    {
        return $this->base32(random_bytes(20));
    }

    /** Kimlik doğrulayıcı uygulamaların okuduğu adres. */
    public function uri(string $secret, string $hesap, string $yayinci): string
    {
        return 'otpauth://totp/'.rawurlencode($yayinci).':'.rawurlencode($hesap)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($yayinci)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP;
    }

    /** Verilen anın kodu; sınama ve test için. */
    public function code(string $secret, ?int $an = null): string
    {
        return $this->step($secret, intdiv($an ?? time(), self::STEP));
    }

    /**
     * Kod geçerli mi?
     *
     * Karşılaştırma hash_equals ile: eşitlik sınamasının süresi koda göre
     * değişmemeli.
     */
    public function verify(string $secret, string $kod, ?int $an = null): bool
    {
        return $this->eslesenAdim($secret, $kod, $an) !== null;
    }

    /**
     * Kod hangi ADIMA denk geldi?
     *
     * Bir kod, pencere yüzünden 90 saniyeye kadar geçerli kalıyor
     * (30 saniyelik adım artı iki komşu). "Geçerli mi" sorusunun cevabı bu
     * sürede değişmediği için aynı kod birden çok kez kabul edilebilirdi;
     * çağıran taraf kodun TEKRAR kullanılmadığını ancak hangi adıma denk
     * geldiğini bilirse denetleyebilir (bkz. TwoStep::dogrula).
     *
     * RFC 6238 §5.2 de bunu istiyor: doğrulanan bir adım bir daha kabul
     * edilmemeli.
     *
     * @return int|null Eşleşen adım; eşleşme yoksa null.
     */
    public function eslesenAdim(string $secret, string $kod, ?int $an = null): ?int
    {
        $kod = preg_replace('/\D/', '', $kod) ?? '';

        if (strlen($kod) !== self::DIGITS) {
            return null;
        }

        $simdi = intdiv($an ?? time(), self::STEP);

        for ($kayma = -self::WINDOW; $kayma <= self::WINDOW; $kayma++) {
            $adim = $simdi + $kayma;

            if (hash_equals($this->step($secret, $adim), $kod)) {
                return $adim;
            }
        }

        return null;
    }

    /** Bir adımın geçerli kalabileceği en uzun süre (saniye). */
    public function pencereSaniyesi(): int
    {
        return self::STEP * (self::WINDOW * 2 + 1);
    }

    /**
     * Kurtarma kodları.
     *
     * Telefon kaybolduğunda tek çıkış yolu. Her biri BİR KEZ kullanılır;
     * kullanılan kod listeden düşer.
     *
     * @return array<int, string>
     *
     * @throws RandomException
     */
    public function recoveryCodes(int $adet = 10): array
    {
        return array_map(
            fn (): string => strtolower(bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3))),
            range(1, $adet),
        );
    }

    /** Tek bir adımın kodu. */
    private function step(string $secret, int $adim): string
    {
        $ozet = hash_hmac('sha1', pack('J', $adim), $this->base32Decode($secret), true);

        /* Son dört bit nereden okuyacağımızı söyler (RFC 6238, "dynamic truncation"). */
        $kaydirma = ord($ozet[strlen($ozet) - 1]) & 0x0F;

        $sayi = ((ord($ozet[$kaydirma]) & 0x7F) << 24)
            | ((ord($ozet[$kaydirma + 1]) & 0xFF) << 16)
            | ((ord($ozet[$kaydirma + 2]) & 0xFF) << 8)
            | (ord($ozet[$kaydirma + 3]) & 0xFF);

        return str_pad((string) ($sayi % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32(string $ham): string
    {
        $bit = '';

        foreach (str_split($ham) as $bayt) {
            $bit .= str_pad(decbin(ord($bayt)), 8, '0', STR_PAD_LEFT);
        }

        $sonuc = '';

        foreach (str_split($bit, 5) as $parca) {
            $sonuc .= self::ALPHABET[bindec(str_pad($parca, 5, '0', STR_PAD_RIGHT))];
        }

        return $sonuc;
    }

    private function base32Decode(string $base32): string
    {
        $bit = '';

        foreach (str_split(strtoupper(rtrim($base32, '='))) as $harf) {
            $sira = strpos(self::ALPHABET, $harf);

            if ($sira === false) {
                continue;
            }

            $bit .= str_pad(decbin($sira), 5, '0', STR_PAD_LEFT);
        }

        $sonuc = '';

        foreach (str_split($bit, 8) as $parca) {
            if (strlen($parca) === 8) {
                $sonuc .= chr(bindec($parca));
            }
        }

        return $sonuc;
    }
}
