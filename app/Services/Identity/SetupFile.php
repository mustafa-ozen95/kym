<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\OauthClient;

/**
 * Projeye konacak TEK dosyayı üretir.
 *
 * NEDEN TEK DOSYA: önceki yol altı dosya kopyalatıyordu (config, iki servis,
 * model, controller, göç) ve her biri projeye göre elle uyarlanıyordu. Bir
 * kimlik sağlayıcıya bağlanmak bu kadar iş olmamalı — Google ile girişte de
 * yapılan şey bir kimlik/sır yazmak ve bir düğme koymaktır.
 *
 * Üretilen dosya kendi kendine yeter: rotalarını kendi kaydeder, düğmesini
 * kendi çizer (biçemi içinde gömülü), eşleştirmeyi kendi yapar. Projede
 * yapılacaklar üçe iner — dosyayı at, sağlayıcıyı kaydet, düğmeyi koy.
 *
 * GÖÇ YOK: eşleşme doğrulanmış e-posta ile kurulur. Merkezde adres, hesabı
 * etkinleştiren yöneticinin onayıyla doğrulanmış sayılıyor; ayrı bir bağ
 * tablosu yalnızca "kişi merkezde e-postasını değiştirirse bağ kopmasın"
 * içindi ve bunun bedeli her projeye bir göç eklemekti.
 */
class SetupFile
{
    public const FILE_NAME = 'IdentityCenter.php';

    public function icerik(OauthClient $istemci, ?string $sir = null): string
    {
        $adresler = (array) $istemci->redirect_uris;
        $donus = $adresler[0] ?? '';

        /*
         * ÜRETİLEN DOSYA PHP KODUDUR; içine giren her değer VERİ olarak girer.
         *
         * Uygulama adı ve dönüş adresi yöneticinin yazdığı metindir ve dosya
         * panelin içinde çalışır. Tek tırnağın içine olduğu gibi konan bir
         * metin tırnağı kapatıp kod yazabilirdi. Bu yüzden değerler
         * var_export ile PHP dize sabiti olarak basılır (kalıpta çevresinde
         * tırnak YOK); yorum satırına giren iki değer ise ayrıca yorumdan
         * çıkamayacak hâle getirilir.
         */
        return strtr($this->kalip(), [
            '{{CENTER_NAME_CONST}}' => var_export((string) config('app.name'), true),
            '{{CENTER_NAME}}' => $this->yorumaGuvenli((string) config('app.name')),
            '{{CENTER_URL}}' => var_export(rtrim((string) config('app.url'), '/'), true),
            '{{CLIENT_ID}}' => var_export((string) $istemci->getKey(), true),
            '{{SECRET}}' => $this->yorumaGuvenli($sir ?? 'BURAYA_SIR'),
            '{{REDIRECT_URI}}' => var_export($donus, true),
            '{{APPLICATION}}' => var_export((string) $istemci->name, true),
        ]);
    }

    /**
     * Yorum içine girecek metinden yorumu kapatan ve satır bölen her şeyi atar.
     *
     * Yorumun içinde tırnaklamak işe yaramaz: dize sabiti yorumda yoktur,
     * `*` + `/` dizisi nerede olursa yorumu bitirir.
     */
    private function yorumaGuvenli(string $metin): string
    {
        return str_replace(['*/', "\r", "\n"], ['* /', ' ', ' '], $metin);
    }

    private function kalip(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * {{CENTER_NAME}} ile giriş.
 *
 * KURULUM (üç adım):
 *
 *   1. Bu dosyayı app/IdentityCenter.php olarak koyun.
 *   2. bootstrap/providers.php içine bir satır: App\IdentityCenter::class,
 *   3. Giriş ekranınıza düğmeyi koyun:  @identityLogin
 *
 * Sır .env'den okunur; aşağıdaki varsayılanlar yalnızca yedektir:
 *
 *   IDENTITY_ENABLED=true
 *   IDENTITY_CLIENT_SECRET={{SECRET}}
 *
 * MERKEZDE HESABI OLAN HERKES BURAYA GİREMEZ. Varsayılan: yerelde eşleşen
 * hesap yoksa giriş reddedilir. İsterseniz otomatik hesap açılsın — ama ETKİN
 * DEĞİL, yöneticinizin onayını bekleyerek:
 *
 *   IDENTITY_AUTO_ACCOUNT=true
 *
 * ve ardından hesabın NASIL doğacağı. İki kip var:
 *
 *  1) MERKEZİN ONAYI YETER (önerilen). Rol eşlemesi yazılır; hesap etkin
 *     doğar ve merkezdeki rolün projedeki karşılığını alır:
 *
 *       IDENTITY_ROLE_FIELD=role_id
 *       IDENTITY_ROLE_MAP={"network":3,"teknik":4}
 *
 *     Eşleme verildiğinde kişinin merkezdeki rolü ŞART olur: rolsüz ya da
 *     eşlemede karşılığı bulunmayan kimlikle hesap AÇILMAZ. Rol atamak, o
 *     kişiye bakılmış olmasının kendisidir; tahmin edilmez.
 *
 *  2) PROJE AYRICA ONAY İSTİYOR. Hesabın "beklemede" alanları yazılır ve
 *     hesap etkin doğmaz; yöneticiniz onaylayana kadar kimse giremez:
 *
 *       IDENTITY_NEW_ACCOUNT={"status":"passive"}
 *
 * İkisi birlikte de yazılabilir: rol atanır, hesap yine onay bekler. En az
 * biri gerekir — ikisi de boşken hesap açılmaz, çünkü hangi alanlarla
 * doğacağını tahmin etmek, içeri girmemesi gereken birini etkin bir hesapla
 * karşılamak olurdu.
 *
 * BU AKIŞ PROJENİN KENDİ GİRİŞİNİ DEĞİŞTİRMEZ. Parolayla giriş yerinde kalır;
 * merkez erişilemez olduğunda panele girilebilmeli.
 *
 * PAROLA MERKEZE YAZILIR, BURAYA HİÇ UĞRAMAZ: kullanıcı merkezin kendi
 * sayfasında giriş yapar, buraya tek kullanımlık bir kod döner. Bu yüzden
 * düğme TAM SAYFA yönlendirir; bir pencere içinde sorulsaydı kullanıcı
 * parolasını yazarken adres çubuğunda merkezi göremezdi.
 */
class IdentityCenter extends ServiceProvider
{
    /** Merkezde kayıtlı bilgiler. .env varsa o kazanır. */
    private const URL = {{CENTER_URL}};

    private const CLIENT_ID = {{CLIENT_ID}};

    private const REDIRECT_URI = {{REDIRECT_URI}};

    private const NAME = {{CENTER_NAME_CONST}};

    /** Bu istemcinin merkezdeki adı; yalnızca bilgi, kod buna dayanmaz. */
    private const APPLICATION = {{APPLICATION}};

    /** Oturumda tutulan akış bilgileri. */
    private const STATE = 'identity_state';

    private const VERIFIER = 'identity_verifier';

    public function boot(): void
    {
        /*
         * Rotalar `guest` altında: oturumu olan birinin yeniden giriş akışına
         * girmesi anlamsız. Kapalı kurulumda uçlar 404 döner, yani
         * yapılandırılmamış bir kurulum yarım çalışmaz.
         */
        Route::middleware(['web', 'guest'])->group(function (): void {
            Route::get('login/center', fn () => $this->start())->name('identity.login');
            Route::get('login/center/callback', fn () => $this->callback())->name('identity.callback');
        });

        /* Giriş ekranında tek satır: @identityLogin */
        Blade::directive('identityLogin', fn (): string => '<?php echo \App\IdentityCenter::button(); ?>');
    }

    /* ── Yapılandırma ── */

    public static function isEnabled(): bool
    {
        return (bool) env('IDENTITY_ENABLED', false) && filled(self::secret()) && filled(self::clientId());
    }

    private static function baseUrl(): string
    {
        return rtrim((string) env('IDENTITY_URL', self::URL), '/');
    }

    private static function clientId(): string
    {
        return (string) env('IDENTITY_CLIENT_ID', self::CLIENT_ID);
    }

    private static function secret(): string
    {
        return (string) env('IDENTITY_CLIENT_SECRET', '');
    }

    private static function redirectUri(): string
    {
        return (string) env('IDENTITY_REDIRECT_URI', self::REDIRECT_URI);
    }

    /* ── Düğme ── */

    /**
     * Giriş düğmesi — biçemi İÇİNDE.
     *
     * Ayrı bir CSS dosyası istemez: projenin derleme düzenine dokunmadan
     * çalışsın diye ölçüler burada duruyor. Renkler projeden miras alınır
     * (currentColor ve saydam zemin), böylece açık ve koyu temada da yerini
     * bulur.
     */
    public static function button(): string
    {
        if (! self::isEnabled()) {
            return '';
        }

        $adres = route('identity.login');
        $ad = e((string) env('IDENTITY_NAME', self::NAME));

        return <<<HTML
        <style>
        .kimlik-button{display:flex;align-items:center;justify-content:center;gap:.5rem;
        width:100%;min-height:2.25rem;padding:0 .75rem;border:1px solid currentColor;
        border-radius:4px;color:inherit;font:inherit;font-weight:600;text-decoration:none;
        opacity:.92;transition:opacity 90ms ease}
        .kimlik-button:hover{opacity:1}
        .identity-divider{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;
        gap:.5rem;margin:1rem 0;font-size:.75rem;opacity:.6}
        .identity-divider::before,.identity-divider::after{content:'';height:1px;background:currentColor;opacity:.35}
        </style>
        <a href="{$adres}" class="kimlik-button">{$ad} ile giriş</a>
        <div class="identity-divider"><span>veya</span></div>
        HTML;
    }

    /* ── Akış ── */

    private function start()
    {
        abort_unless(self::isEnabled(), 404);

        /* RFC 7636: 43-128 karakter, URL-güvenli. */
        $dogrulayici = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $durum = Str::random(40);

        /* İkisi de OTURUMDA kalır: geri dönen isteğin bizim akışımız olduğunu böyle biliriz. */
        session()->put(self::VERIFIER, $dogrulayici);
        session()->put(self::STATE, $durum);

        $meydanOkuma = rtrim(strtr(base64_encode(hash('sha256', $dogrulayici, true)), '+/', '-_'), '=');

        return redirect()->away(self::baseUrl().'/oauth/authorize?'.http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => '',
            'state' => $durum,
            'code_challenge' => $meydanOkuma,
            'code_challenge_method' => 'S256',
        ]));
    }

    private function callback()
    {
        abort_unless(self::isEnabled(), 404);

        $beklenen = session()->pull(self::STATE);
        $dogrulayici = session()->pull(self::VERIFIER);

        /*
         * DURUM EŞLEŞMELİ (CSRF).
         *
         * Eşleşmiyorsa bu akışı biz başlatmamışız demektir: saldırgan, kendi
         * kodunu kurbanın tarayıcısında kullandırıp onu kendi hesabına
         * bağlayabilirdi.
         */
        if (blank($beklenen) || ! hash_equals((string) $beklenen, (string) request()->query('state'))) {
            return $this->fail('Giriş akışı doğrulanamadı; lütfen yeniden deneyin.');
        }

        if (blank(request()->query('code')) || blank($dogrulayici)) {
            return $this->fail('Merkezden kod gelmedi.');
        }

        try {
            $jeton = $this->http()->asForm()->post(self::baseUrl().'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => self::clientId(),
                'client_secret' => self::secret(),
                'redirect_uri' => self::redirectUri(),
                'code_verifier' => (string) $dogrulayici,
                'code' => (string) request()->query('code'),
            ])->throw()->json();

            $kimlik = $this->http()
                ->withToken((string) ($jeton['access_token'] ?? ''))
                ->acceptJson()
                ->get(self::baseUrl().'/oauth/userinfo')
                ->throw()
                ->json();
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Merkezle bağlantı kurulamadı; lütfen parolanızla girin.');
        }

        $kullanici = $this->match($kimlik);

        /*
         * Eşleşme yoksa VARSAYILAN OLARAK hesap açılmaz.
         *
         * Merkezde hesabı olmak, bu projede işi olmak demek değildir. Hesabı
         * projenin yöneticisi açar.
         *
         * IDENTITY_AUTO_ACCOUNT=true ise hesap açılır ama ETKİN DEĞİL: proje
         * kendi "beklemede" alanlarını IDENTITY_NEW_ACCOUNT ile söyler ve
         * yöneticisi onaylayana kadar kimse içeri giremez. Böylece yönetici
         * ad/e-posta yazmak yerine yalnızca onaylıyor.
         */
        if (! $kullanici) {
            $kullanici = $this->createAccount($kimlik);
        }

        if (! $kullanici) {
            return $this->fail('Bu kimlikle eşleşen bir hesap yok. Hesap açma isteği için sistem yöneticinize başvurun.');
        }

        Auth::login($kullanici, true);
        session()->regenerate();

        return redirect()->intended($this->home());
    }

    /**
     * Merkezden gelen kimliği yerel hesapla eşleştirir.
     *
     * DOĞRULANMIŞ ADRES ŞART ve yerelde TEK karşılığı olmalı. Doğrulanmamış
     * bir adresle eşleştirmek, meslektaşının e-postasıyla merkezde hesap açan
     * birinin onun yerine girmesi demekti; birden fazla karşılık varsa da
     * hangisinin kastedildiği belli değildir ve tahmin edilmez.
     *
     * @param  array<string, mixed>  $kimlik
     */
    private function match(array $kimlik)
    {
        if (blank($kimlik['email'] ?? null) || ! ($kimlik['email_verified'] ?? false)) {
            return null;
        }

        /** @var class-string $model */
        $model = config('auth.providers.users.model');

        $adaylar = $model::where('email', $kimlik['email'])->limit(2)->get();

        if ($adaylar->count() !== 1) {
            return null;
        }

        $kullanici = $adaylar->first();

        return $this->isActive($kullanici) ? $kullanici : null;
    }

    /**
     * Eşleşmeyen kimlik için hesap açar.
     *
     * Kapalıysa (varsayılan) null döner ve giriş reddedilir.
     *
     * Açıkken hesabın NASIL doğacağını proje söyler; tahmin edilmez:
     *
     *  - IDENTITY_ROLE_MAP verilmişse hesap ETKİN doğar ve merkezdeki rolün
     *    karşılığını alır. Onay zaten merkezde verilmiştir: kişi orada
     *    onaylanmış ve kendisine bir rol atanmıştır.
     *  - IDENTITY_NEW_ACCOUNT verilmişse o alanlar yazılır; proje ikinci bir onay
     *    istiyorsa hesabı burada "beklemede" doğurur ({"status":"passive"}).
     *
     * @param  array<string, mixed>  $kimlik
     */
    private function createAccount(array $kimlik)
    {
        if (! (bool) env('IDENTITY_AUTO_ACCOUNT', false)) {
            return null;
        }

        $alanlar = json_decode((string) env('IDENTITY_NEW_ACCOUNT', ''), true);
        $alanlar = is_array($alanlar) ? $alanlar : [];

        $rol = $this->roleField($kimlik);

        /* Rol eşlemesi verilmiş ama bu kimlik karşılamıyorsa hesap açılmaz. */
        if ($rol === false) {
            return null;
        }

        /*
         * EN AZ BİRİ GEREKİR: ya rol eşlemesi ya "beklemede" alanları.
         *
         * İkisi de boşken hesabın hangi alanlarla doğacağını tahmin etmek
         * gerekirdi ve yanlış tahmin, içeri girmemesi gereken birini etkin bir
         * hesapla karşılamak olurdu.
         */
        if ($alanlar === [] && $rol === []) {
            report(new \RuntimeException(
                'IDENTITY_AUTO_ACCOUNT açık ama ne IDENTITY_ROLE_MAP ne IDENTITY_NEW_ACCOUNT yazılmış; hesap açılmadı.'
            ));

            return null;
        }

        /** @var class-string $model */
        $model = config('auth.providers.users.model');

        $kullanici = $model::create([
            'name' => $kimlik['name'] ?: $kimlik['email'],
            'email' => $kimlik['email'],

            /* Parola KULLANILMAZ: bu hesap merkezle giriyor. Rastgele ve bilinmez. */
            'password' => bcrypt(Str::random(64)),
            ...$alanlar,
            ...$rol,
        ]);

        /* Bekleyen hesapla oturum açılmaz; kullanıcı ne olduğunu bilmeli. */
        return $this->isActive($kullanici) ? $kullanici : null;
    }

    /**
     * Merkezdeki rolün projedeki karşılığı.
     *
     * İSTEĞE BAĞLIDIR: IDENTITY_ROLE_FIELD ve IDENTITY_ROLE_MAP verilmemişse boş
     * dizi döner ve hesap yalnızca IDENTITY_NEW_ACCOUNT alanlarıyla açılır.
     *
     * Verilmişse ROL ŞART OLUR: merkezde rolü olmayan ya da eşlemede karşılığı
     * bulunmayan kimlik için `false` döner ve hesap açılmaz. Karşılığı
     * olmayan bir rolü varsayılana çevirmek, kimin ne yetkiyle içeri girdiğini
     * belirsizleştirirdi.
     *
     * @param  array<string, mixed>  $kimlik
     * @return array<string, mixed>|false
     */
    private function roleField(array $kimlik): array|false
    {
        $alan = (string) env('IDENTITY_ROLE_FIELD', '');
        $esleme = json_decode((string) env('IDENTITY_ROLE_MAP', ''), true);

        if ($alan === '' || ! is_array($esleme) || $esleme === []) {
            return [];
        }

        $slug = (string) ($kimlik['role'] ?? '');

        if ($slug === '' || ! array_key_exists($slug, $esleme)) {
            report(new \RuntimeException(
                "Merkezdeki rol '{$slug}' IDENTITY_ROLE_MAP içinde yok; hesap açılmadı."
            ));

            return false;
        }

        return [$alan => $esleme[$slug]];
    }

    /**
     * Projenin kendi "etkin mi" kuralı.
     *
     * Bilinen iki ad denenir; ikisi de yoksa karar projeye bırakılır ve giriş
     * engellenmez — burada bilinmeyen bir alanı tahmin etmek, çalışan bir
     * hesabı sessizce kilitlemek olurdu.
     */
    private function isActive($kullanici): bool
    {
        foreach (['isActive', 'girebilir'] as $yontem) {
            if (method_exists($kullanici, $yontem) && ! $kullanici->{$yontem}()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merkeze giden her istek aynı doğrulamayı kullanır.
     *
     * Sertifika kökü yapılandırılmışsa yalnızca ONA güvenilir. Hiçbir dalda
     * doğrulama kapatılmaz: jeton takasında araya giren biri istemci sırrını
     * okur.
     */
    private function http()
    {
        $istek = Http::timeout(15);
        $kok = env('IDENTITY_CA_CERT');

        return filled($kok) ? $istek->withOptions(['verify' => $kok]) : $istek;
    }

    /** Giriş sonrası gidilecek yer; proje kendi rotasını verebilir. */
    private function home(): string
    {
        $rota = (string) env('IDENTITY_HOME', '');

        return $rota !== '' && Route::has($rota) ? route($rota) : '/';
    }

    private function fail(string $mesaj)
    {
        $giris = Route::has('login') ? route('login') : '/';

        return redirect()->to($giris)->withErrors(['email' => $mesaj]);
    }
}
PHP;
    }
}
