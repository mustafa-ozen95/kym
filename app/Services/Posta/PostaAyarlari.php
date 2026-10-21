<?php

declare(strict_types=1);

namespace App\Services\Posta;

use App\Mail\DenemeIletisi;
use App\Models\Ayar;
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
class PostaAyarlari
{
    public const SUNUCU = 'posta.sunucu';

    public const PORT = 'posta.port';

    public const SIFRELEME = 'posta.sifreleme';

    public const KULLANICI = 'posta.kullanici';

    public const PAROLA = 'posta.parola';

    public const GONDEREN = 'posta.gonderen';

    public const GONDEREN_AD = 'posta.gonderen_ad';

    /** Deneme postası başarıyla gittiğinde damgalanır. */
    public const SINANDI = 'posta.sinandi';

    /** @var list<string> */
    public const SIFRELEME_SECENEKLERI = ['tls', 'ssl', 'yok'];

    /**
     * Ekranda gösterilecek değerler. Parola YOK.
     *
     * @return array{sunucu: ?string, port: ?string, sifreleme: string, kullanici: ?string, gonderen: ?string, gonderen_ad: ?string, parola_var: bool, sinandi: ?string}
     */
    public function goster(): array
    {
        $ayarlar = Ayar::hepsi();

        return [
            'sunucu' => $ayarlar[self::SUNUCU] ?? null,
            'port' => $ayarlar[self::PORT] ?? null,
            'sifreleme' => $ayarlar[self::SIFRELEME] ?? 'tls',
            'kullanici' => $ayarlar[self::KULLANICI] ?? null,
            'gonderen' => $ayarlar[self::GONDEREN] ?? null,
            'gonderen_ad' => $ayarlar[self::GONDEREN_AD] ?? null,
            'parola_var' => filled($ayarlar[self::PAROLA] ?? null),
            'sinandi' => $ayarlar[self::SINANDI] ?? null,
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
        $onceki = Ayar::hepsi();
        $hedefDegisti = $this->hedef($onceki[self::SUNUCU] ?? null) !== $this->hedef($veri['sunucu'] ?? null)
            || $this->hedef($onceki[self::PORT] ?? null) !== $this->hedef($veri['port'] ?? null)
            || $this->hedef($onceki[self::KULLANICI] ?? null) !== $this->hedef($veri['kullanici'] ?? null);

        if ($hedefDegisti && ! filled($veri['parola'] ?? null)) {
            Ayar::yaz(self::PAROLA, '');
        }

        Ayar::yaz(self::SUNUCU, (string) $veri['sunucu']);
        Ayar::yaz(self::PORT, (string) $veri['port']);
        Ayar::yaz(self::SIFRELEME, (string) $veri['sifreleme']);
        Ayar::yaz(self::KULLANICI, (string) ($veri['kullanici'] ?? ''));
        Ayar::yaz(self::GONDEREN, (string) $veri['gonderen']);
        Ayar::yaz(self::GONDEREN_AD, (string) $veri['gonderen_ad']);

        /*
         * Boş parola "sil" demek DEĞİL.
         *
         * Ekranda parola hiç görünmediği için, başka bir alanı düzeltmek
         * isteyen biri her kaydedişte parolayı sıfırlardı.
         */
        if (filled($veri['parola'] ?? null)) {
            Ayar::yaz(self::PAROLA, Crypt::encryptString((string) $veri['parola']));
        }

        /* Ayar değişti: eski denemenin sonucu artık bir şey kanıtlamıyor. */
        Ayar::yaz(self::SINANDI, '');
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

        return filled($ayarlar['sunucu']) && filled($ayarlar['port']) && filled($ayarlar['gonderen']);
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
            'mail.mailers.smtp.host' => $ayarlar['sunucu'],
            'mail.mailers.smtp.port' => (int) $ayarlar['port'],
            'mail.mailers.smtp.scheme' => $ayarlar['sifreleme'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $ayarlar['kullanici'] ?: null,
            'mail.mailers.smtp.password' => $this->parola(),
            'mail.from.address' => $ayarlar['gonderen'],
            'mail.from.name' => $ayarlar['gonderen_ad'] ?: config('app.name'),
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
            Mail::to($alici)->send(new DenemeIletisi);
        } catch (Throwable $e) {
            Ayar::yaz(self::SINANDI, '');

            return [false, $this->insanca($e)];
        }

        Ayar::yaz(self::SINANDI, now()->toDateTimeString());

        return [true, 'Deneme iletisi '.$alici.' adresine gönderildi.'];
    }

    private function parola(): ?string
    {
        $sifreli = Ayar::hepsi()[self::PAROLA] ?? null;

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
