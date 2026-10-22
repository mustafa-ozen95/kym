<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\OauthClient;

/**
 * Bir siteyi merkeze bağlamanın anlatımı.
 *
 * NEDEN KODDA: bu bilgi bir kez bir belgeye yazılıp unutulursa, ilk adres
 * değişikliğinde yanlış talimat vermeye başlar. Buradan üretildiğinde merkezin
 * GERÇEK adresini, gerçek istemci kimliğini ve gerçek dönüş adresini taşır;
 * yanlış olması için kurulumun kendisinin yanlış olması gerekir.
 *
 * İKİ YOL VAR ve ikisi de eksiksiz anlatılır:
 *
 *  - Laravel projeleri için hazır tek dosya (üç adım).
 *  - Başka dillerdeki projeler için PROTOKOL. Merkez standart OAuth2 +
 *    OpenID Connect keşfi konuşuyor; Node, Python, Go — hepsinin kendi
 *    kütüphanesi bu adresleri okuyup bağlanabilir. "Bizim dosyamız yoksa
 *    bağlanamazsın" izlenimi vermek yanlış olurdu.
 *
 * Sır BURADA DURMAZ: yalnızca kayıt anında bir kez gösterilir (bkz.
 * ClientController).
 */
class IntegrationGuide
{
    /**
     * Projenin .env dosyasına yazılacak blok.
     *
     * Kopyala-yapıştır olarak çalışır; tek eksik sır.
     */
    public function envBlogu(OauthClient $istemci, ?string $sir = null, ?string $bildirimSirri = null): string
    {
        $adresler = (array) $istemci->redirect_uris;
        $adres = $adresler[0] ?? 'https://ornek.ornek.test/login/center/callback';

        return implode("\n", [
            '# '.config('app.name').' ile giriş.',
            '# İstemci bilgileri yoksa özellik kapalıdır; ayardaki tik onu açamaz.',
            'IDENTITY_ENABLED=true',
            'IDENTITY_URL='.rtrim((string) config('app.url'), '/'),
            'IDENTITY_CLIENT_ID='.$istemci->getKey(),
            'IDENTITY_CLIENT_SECRET='.($sir ?? '<sır kayıt anında bir kez gösterildi; yenileyebilirsiniz>'),
            'IDENTITY_REDIRECT_URI='.$adres,
            '',
            '# Merkez, rol ya da durum değişince projeye ANINDA haber verir ve',
            '# bildirimi bu sırla imzalar. Boş bırakılırsa bildirim kabul edilmez;',
            '# proje değişikliği beş dakikalık eşitleme turunda öğrenir.',
            'IDENTITY_NOTIFY_SECRET='.($bildirimSirri ?? (string) ($istemci->getAttributes()['bildirim_sirri'] ?? '')),
            'IDENTITY_NAME="'.config('app.name').'"',
            '',
            '# Giriş sonrası gidilecek rota adı (projeye göre).',
            'IDENTITY_HOME=dashboard',
            '',
            '# Yerelde hesabı olmayan biri geldiğinde ne olsun?',
            '# Varsayılan: giriş reddedilir, hesabı yönetici açar.',
            '# IDENTITY_AUTO_ACCOUNT=true',
            '',
            '# 1) Merkezin onayı yeter (önerilen). Hesap ETKİN doğar ve merkezdeki',
            '#    rolün buradaki karşılığını alır. Onay zaten merkezde verildi:',
            '#    kişi orada onaylandı ve kendisine bir rol atandı. Eşleme yazılınca',
            '#    ROL ŞART OLUR — rolsüz ya da eşlemede karşılığı olmayan kimlikle',
            '#    hesap açılmaz.',
            '# IDENTITY_ROLE_FIELD=role_id',
            '# IDENTITY_ROLE_MAP={"network":3,"teknik":4}',
            '',
            '# 2) Proje AYRICA onay istiyorsa: hesabın "beklemede" alanlarını yazın;',
            '#    hesap etkin doğmaz, yöneticiniz onaylayana kadar kimse giremez.',
            '#    Bu alanlar tahmin edilmez, siz yazarsınız.',
            '# IDENTITY_NEW_ACCOUNT={"status":"passive"}',
            '',
            '# Merkezin sertifikası genel bir otoriteden değilse doğrulama KAPATILMAZ,',
            '# yalnızca güvenilen kök daraltılır. Sertifikanın kopyasını projeye koyun.',
            'IDENTITY_CA_CERT=',
        ]);
    }

    /**
     * Laravel projeleri: üç adım.
     *
     * @return list<array{baslik: string, aciklama: string}>
     */
    public function laravelAdimlari(): array
    {
        return [
            [
                'baslik' => 'Dosyayı indirin',
                'aciklama' => 'Aşağıdaki düğme, o uygulamaya özel hazırlanmış tek bir dosya verir: '
                    .'app/IdentityCenter.php. İçinde rotalar, düğme, akış ve eşleştirme birlikte '
                    .'gelir; başka dosya, göç ya da paket gerekmez.',
            ],
            [
                'baslik' => 'Sağlayıcıyı kaydedin',
                'aciklama' => 'bootstrap/providers.php içine tek satır: App\IdentityCenter::class,',
            ],
            [
                'baslik' => 'Kimin girebileceğine karar verin',
                'aciklama' => 'Varsayılan: merkezde hesabı olan biri, bu projede de hesabı VARSA girer; '
                    .'yoksa giriş reddedilir ve hesabı yönetici açar. IDENTITY_AUTO_ACCOUNT=true ile '
                    .'hesap kendiliğinden açılır. Önerilen kip, rol eşlemesini yazmaktır '
                    .'(IDENTITY_ROLE_FIELD + IDENTITY_ROLE_MAP): hesap ETKİN doğar ve merkezdeki rolün '
                    .'buradaki karşılığını alır — onay zaten merkezde verilmiştir, kişi orada '
                    .'onaylanmış ve kendisine bir rol atanmıştır; rolsüz kimlik yine içeri alınmaz. '
                    .'Projeniz İKİNCİ bir onay istiyorsa IDENTITY_NEW_ACCOUNT ile hesabı "beklemede" '
                    .'doğurursunuz.',
            ],
            [
                'baslik' => 'Düğmeyi koyun',
                'aciklama' => 'Giriş ekranınızda tek satır: @identityLogin — düğme kendi biçemini '
                    .'taşır, projenin CSS düzenine dokunmaz. Parolayla giriş formunuz yerinde '
                    .'kalır; merkez erişilemez olduğu gün panele girmenin tek yolu odur.',
            ],
        ];
    }

    /**
     * Herhangi bir dil: protokolün kendisi.
     *
     * @return list<array{alan: string, deger: string, not: string}>
     */
    public function protokol(OauthClient $istemci): array
    {
        $adresler = (array) $istemci->redirect_uris;

        return [
            [
                'alan' => 'Keşif belgesi',
                'deger' => route('identity.discovery'),
                'not' => 'Çoğu kütüphane yalnızca bunu ister; uçları buradan okur.',
            ],
            [
                'alan' => 'issuer',
                'deger' => rtrim((string) config('app.url'), '/'),
                'not' => '',
            ],
            [
                'alan' => 'authorization_endpoint',
                'deger' => route('passport.authorizations.authorize'),
                'not' => 'Kullanıcı buraya YÖNLENDİRİLİR; parola burada girilir.',
            ],
            [
                'alan' => 'token_endpoint',
                'deger' => url('/oauth/token'),
                'not' => 'Sunucudan sunucuya; sır burada kullanılır.',
            ],
            [
                'alan' => 'userinfo_endpoint',
                'deger' => route('identity.userinfo'),
                'not' => 'Bearer jetonla çağrılır; sub, name, email, email_verified döner.',
            ],
            [
                'alan' => 'client_id',
                'deger' => (string) $istemci->getKey(),
                'not' => '',
            ],
            [
                'alan' => 'redirect_uri',
                'deger' => implode(', ', $adresler),
                'not' => 'BİREBİR eşleşmeli; başka bir adres kabul edilmez.',
            ],
            [
                'alan' => 'response_type',
                'deger' => 'code',
                'not' => 'Tek desteklenen akış: Authorization Code.',
            ],
            [
                'alan' => 'code_challenge_method',
                'deger' => 'S256',
                'not' => 'PKCE ZORUNLU. Kod adres çubuğunda taşınıyor; doğrulayıcıyı bilmeyen onu jetona çeviremez.',
            ],

            /*
             * GİRİŞ YETMEZ: rol ve durum sonradan da değişir.
             *
             * Bu iki satır olmadan bağlanan bir proje, erişimi kaldırılan
             * kişiyi içeride tutmaya devam eder. Protokol listesinde durmaları
             * bu yüzden: "bağlanmak" girişten ibaret değil.
             */
            [
                'alan' => 'Durum ucu (arka kanal)',
                'deger' => url('/api/identity-status'),
                'not' => 'POST client_id + client_secret + identities[] (sub listesi). '
                    .'Her kimlik için status, can_enter ve role döner. Projeniz bunu düzenli '
                    .'aralıklarla sormalı; biz beş dakikada bir soruyoruz.',
            ],
            [
                'alan' => 'Değişiklik bildirimi',
                'deger' => 'POST {proje kökü}/api/identity/refresh',
                'not' => 'Rol ya da durum değişince merkez bu adrese {"subject": "..."} gönderir. '
                    .'Başlık: X-Identity-Signature = HMAC-SHA256(subject, bildirim sırrı). '
                    .'GÖVDEYE GÜVENMEYİN: yeni değeri taşımaz, yalnızca "bu kimliği yeniden sor" der.',
            ],
        ];
    }

    /**
     * Node.js örneği.
     *
     * Uzun bir anlatım yerine ÇALIŞAN bir örnek: kurulumu yapan kişi kendi
     * dilindeki karşılığını buradan çıkarabiliyor. Kütüphane seçimi bağlayıcı
     * değil; standart OIDC istemcisi olan her paket aynı işi yapar.
     */
    public function nodeOrnegi(OauthClient $istemci): string
    {
        $adresler = (array) $istemci->redirect_uris;
        $donus = $adresler[0] ?? 'https://ornek.ornek.test/login/center/callback';
        $kesif = route('identity.discovery');

        return <<<JS
        // npm i openid-client express express-session
        import * as client from 'openid-client';

        const kesif = new URL('{$kesif}');
        const yapilandirma = await client.discovery(kesif, '{$istemci->getKey()}', process.env.IDENTITY_CLIENT_SECRET);

        // 1) Giriş: PKCE doğrulayıcısı ve state OTURUMDA kalır.
        app.get('/login/center', async (req, res) => {
          const dogrulayici = client.randomPKCECodeVerifier();
          req.session.dogrulayici = dogrulayici;
          req.session.durum = client.randomState();

          res.redirect(client.buildAuthorizationUrl(yapilandirma, {
            redirect_uri: '{$donus}',
            scope: '',
            state: req.session.durum,
            code_challenge: await client.calculatePKCECodeChallenge(dogrulayici),
            code_challenge_method: 'S256',
          }).href);
        });

        // 2) Dönüş: state EŞLEŞMELİ, yoksa akışı biz başlatmamışız demektir.
        app.get('/login/center/callback', async (req, res) => {
          const jeton = await client.authorizationCodeGrant(yapilandirma, new URL(req.url, '{$donus}'), {
            pkceCodeVerifier: req.session.dogrulayici,
            expectedState: req.session.durum,
          });

          const kimlik = await client.fetchUserInfo(yapilandirma, jeton.access_token, client.skipSubjectCheck);

          // 3) EŞLEŞTİRME: hesap AÇILMAZ, mevcut hesapla eşleşir.
          //    Adres doğrulanmamışsa ya da yerelde tek karşılığı yoksa reddedilir.
          if (!kimlik.email_verified) return res.redirect('/login');

          const kullanici = await kullaniciBul(kimlik.email); // projenin kendi sorgusu
          if (!kullanici) return res.redirect('/login');

          req.session.kullaniciId = kullanici.id;
          res.redirect('/');
        });
        JS;
    }

    /** Keşif belgesi: istemcinin ayarları buradan da okunabilir. */
    public function kesifAdresi(): string
    {
        return route('identity.discovery');
    }
}
