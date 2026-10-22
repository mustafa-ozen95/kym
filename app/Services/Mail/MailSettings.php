<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\TestMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SMTP ayarları — .env yerine EKRANDAN.
 *
 * NEDEN EKRANDAN: posta sunucusu değiştiğinde .env düzenlemek sunucuya
 * erişim ister, değişikliği kimin yaptığı da belli olmaz. Buradan yapılan her
 * değişiklik denetim kaydına düşer.
 *
 * PAROLA AÇIK DURMAZ: veritabanına şifreli yazılır, ekrana hiç basılmaz ve
 * boş bırakıldığında eskisi korunur — "kaydet"e basan biri farkında olmadan
 * parolayı silmesin.
 *
 * Ayar yoksa .env'deki değerler geçerlidir; yani bu ekran yapılandırmayı
 * DEĞİŞTİRİR, zorunlu kılmaz.
 */
class MailSettings
{
    public const SERVER = 'posta.sunucu';

    public const PORT = 'posta.port';

    public const ENCRYPTION = 'posta.sifreleme';

    public const USERNAME = 'posta.kullanici';

    public const PASSWORD = 'posta.parola';

    public const SENDER = 'posta.gonderen';

    public const SENDER_NAME = 'posta.gonderen_ad';

    /** Deneme postası başarıyla gittiğinde damgalanır. */
    public const TESTED = 'posta.sinandi';

    /** @var list<string> */
    public const ENCRYPTION_OPTIONS = ['tls', 'ssl', 'yok'];

    /**
     * Ekranda gösterilecek değerler. Parola YOK.
     *
     * @return array{sunucu: ?string, port: ?string, sifreleme: string, kullanici: ?string, gonderen: ?string, gonderen_ad: ?string, parola_var: bool, sinandi: ?string}
     */
    public function goster(): array
    {
        $ayarlar = Setting::hepsi();

        return [
            'server' => $ayarlar[self::SERVER] ?? null,
            'port' => $ayarlar[self::PORT] ?? null,
            'encryption' => $ayarlar[self::ENCRYPTION] ?? 'tls',
            'username' => $ayarlar[self::USERNAME] ?? null,
            'sender' => $ayarlar[self::SENDER] ?? null,
            'sender_name' => $ayarlar[self::SENDER_NAME] ?? null,
            'password_set' => filled($ayarlar[self::PASSWORD] ?? null),
            'sinandi' => $ayarlar[self::TESTED] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    public function kaydet(array $veri): void
    {
        /*
         * PAROLA GİTTİĞİ YERE BAĞLIDIR.
         *
         * Parola ekranda hiç görünmez ve boş bırakmak "değiştirme" demektir;
         * ama sunucuyu, portu ya da kullanıcıyı değiştiren biri aynı kayıtlı
         * parolayı kendi seçtiği adrese gönderebilirdi (deneme postası SMTP
         * girişi yapar). Hedef değiştiyse ve yeni parola verilmediyse eski
         * parola SİLİNİR: gitmesi gereken yeri bilen, parolayı da yeniden
         * yazabilir.
         */
        $onceki = Setting::hepsi();
        $hedefDegisti = $this->hedef($onceki[self::SERVER] ?? null) !== $this->hedef($veri['server'] ?? null)
            || $this->hedef($onceki[self::PORT] ?? null) !== $this->hedef($veri['port'] ?? null)
            || $this->hedef($onceki[self::USERNAME] ?? null) !== $this->hedef($veri['username'] ?? null);

        if ($hedefDegisti && ! filled($veri['password'] ?? null)) {
            Setting::yaz(self::PASSWORD, '');
        }

        Setting::yaz(self::SERVER, (string) $veri['server']);
        Setting::yaz(self::PORT, (string) $veri['port']);
        Setting::yaz(self::ENCRYPTION, (string) $veri['encryption']);
        Setting::yaz(self::USERNAME, (string) ($veri['username'] ?? ''));
        Setting::yaz(self::SENDER, (string) $veri['sender']);
        Setting::yaz(self::SENDER_NAME, (string) $veri['sender_name']);

        /*
         * Boş parola "sil" demek DEĞİL.
         *
         * Ekranda parola hiç görünmediği için, başka bir alanı düzeltmek
         * isteyen biri her kaydedişte parolayı sıfırlardı.
         */
        if (filled($veri['password'] ?? null)) {
            Setting::yaz(self::PASSWORD, Crypt::encryptString((string) $veri['password']));
        }

        /* Ayar değişti: eski denemenin sonucu artık bir şey kanıtlamıyor. */
        Setting::yaz(self::TESTED, '');
    }

    /** Hedef karşılaştırması için: boşluk ve büyük/küçük harf farkı hedef değişimi sayılmaz. */
    private function hedef(mixed $deger): string
    {
        return strtolower(trim((string) $deger));
    }

    /** Yapılandırma tamam mı? Eksikse posta göndermeyi denemenin anlamı yok. */
    public function hazirMi(): bool
    {
        $ayarlar = $this->goster();

        return filled($ayarlar['server']) && filled($ayarlar['port']) && filled($ayarlar['sender']);
    }

    /** Deneme postası gerçekten gitti mi? */
    public function sinandiMi(): bool
    {
        return filled($this->goster()['sinandi']);
    }

    /**
     * Kayıtlı ayarları çalışan yapılandırmaya bindirir.
     *
     * Ayar yoksa .env'deki mailer olduğu gibi kalır.
     */
    public function uygula(): void
    {
        if (! $this->hazirMi()) {
            return;
        }

        $ayarlar = $this->goster();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $ayarlar['server'],
            'mail.mailers.smtp.port' => (int) $ayarlar['port'],
            'mail.mailers.smtp.scheme' => $ayarlar['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $ayarlar['username'] ?: null,
            'mail.mailers.smtp.password' => $this->parola(),
            'mail.from.address' => $ayarlar['sender'],
            'mail.from.name' => $ayarlar['sender_name'] ?: config('app.name'),
        ]);
    }

    /**
     * Deneme postası gönderir ve sonucu damgalar.
     *
     * SENKRON gönderilir: kullanıcı sonucu ŞİMDİ görmek için basıyor.
     * Kuyruğa alınsaydı ekran "gönderildi" der, hata ise günlüğe düşerdi.
     *
     * @return array{0: bool, 1: string}
     */
    public function sina(string $alici): array
    {
        $this->uygula();

        try {
            Mail::to($alici)->send(new TestMessage);
        } catch (Throwable $e) {
            Setting::yaz(self::TESTED, '');

            return [false, $this->insanca($e)];
        }

        Setting::yaz(self::TESTED, now()->toDateTimeString());

        return [true, 'Deneme iletisi '.$alici.' adresine gönderildi.'];
    }

    private function parola(): ?string
    {
        $sifreli = Setting::hepsi()[self::PASSWORD] ?? null;

        if (blank($sifreli)) {
            return null;
        }

        try {
            return Crypt::decryptString($sifreli);
        } catch (Throwable) {
            /* Uygulama anahtarı değişmişse eski parola çözülemez; parolasız denenir. */
            return null;
        }
    }

    /**
     * Ham istisna kullanıcıya gösterilecek bir metin değildir.
     *
     * Ama tümüyle yutulursa da yönetici neyi düzelteceğini bilemez; bu yüzden
     * sık görülen üç durum ADIYLA söylenir, gerisi kısaltılır.
     */
    private function insanca(Throwable $e): string
    {
        $metin = $e->getMessage();

        return match (true) {
            str_contains($metin, 'authenticate') || str_contains($metin, '535') => 'Sunucu kullanıcı adı veya parolayı kabul etmedi.',
            str_contains($metin, 'Connection could not be established') || str_contains($metin, 'timed out') => 'Posta sunucusuna ulaşılamadı; adres, port ve şifreleme ayarını kontrol edin.',
            str_contains($metin, 'certificate') => 'Sunucunun sertifikası doğrulanamadı.',
            default => 'Gönderilemedi: '.mb_substr($metin, 0, 200),
        };
    }
}
