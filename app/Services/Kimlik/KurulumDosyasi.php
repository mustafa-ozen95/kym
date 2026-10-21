<?php

declare(strict_types=1);

namespace App\Services\Kimlik;

use App\Models\OauthIstemci;

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
class KurulumDosyasi
{
    public const AD = 'KimlikMerkezi.php';

    public function icerik(OauthIstemci $istemci, ?string $sir = null): string
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
            '{{MERKEZ_AD_SABIT}}' => var_export((string) config('app.name'), true),
            '{{MERKEZ_AD}}' => $this->yorumaGuvenli((string) config('app.name')),
            '{{MERKEZ_ADRES}}' => var_export(rtrim((string) config('app.url'), '/'), true),
            '{{ISTEMCI}}' => var_export((string) $istemci->getKey(), true),
            '{{SIR}}' => $this->yorumaGuvenli($sir ?? 'BURAYA_SIR'),
            '{{DONUS}}' => var_export($donus, true),
            '{{UYGULAMA}}' => var_export((string) $istemci->name, true),
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
 * {{MERKEZ_AD}} ile giriş.
 *
 * KURULUM (üç adım):
 *
 *   1. Bu dosyayı app/KimlikMerkezi.php olarak koyun.
 *   2. bootstrap/providers.php içine bir satır: App\KimlikMerkezi::class,
 *   3. Giriş ekranınıza düğmeyi koyun:  @kimlikGiris
 *
 * Sır .env'den okunur; aşağıdaki varsayılanlar yalnızca yedektir:
 *
 *   KIMLIK_ACIK=true
 *   KIMLIK_ISTEMCI_SIR={{SIR}}
 *
 * MERKEZDE HESABI OLAN HERKES BURAYA GİREMEZ. Varsayılan: yerelde eşleşen
 * hesap yoksa giriş reddedilir. İsterseniz otomatik hesap açılsın — ama ETKİN
 * DEĞİL, yöneticinizin onayını bekleyerek:
 *
 *   KIMLIK_OTOMATIK_HESAP=true
 *
 * ve ardından hesabın NASIL doğacağı. İki kip var:
 *
 *  1) MERKEZİN ONAYI YETER (önerilen). Rol eşlemesi yazılır; hesap etkin
 *     doğar ve merkezdeki rolün projedeki karşılığını alır:
 *
 *       KIMLIK_ROL_ALANI=role_id
 *       KIMLIK_ROL_ESLEME={"network":3,"teknik":4}
 *
 *     Eşleme verildiğinde kişinin merkezdeki rolü ŞART olur: rolsüz ya da
 *     eşlemede karşılığı bulunmayan kimlikle hesap AÇILMAZ. Rol atamak, o
 *     kişiye bakılmış olmasının kendisidir; tahmin edilmez.
 *
 *  2) PROJE AYRICA ONAY İSTİYOR. Hesabın "beklemede" alanları yazılır ve
 *     hesap etkin doğmaz; yöneticiniz onaylayana kadar kimse giremez:
 *
 *       KIMLIK_YENI_HESAP={"status":"passive"}
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
class KimlikMerkezi extends ServiceProvider
{
    /** Merkezde kayıtlı bilgiler. .env varsa o kazanır. */
    private const ADRES = {{MERKEZ_ADRES}};

    private const ISTEMCI = {{ISTEMCI}};

    private const DONUS = {{DONUS}};

    private const AD = {{MERKEZ_AD_SABIT}};

    /** Bu istemcinin merkezdeki adı; yalnızca bilgi, kod buna dayanmaz. */
    private const UYGULAMA = {{UYGULAMA}};

    /** Oturumda tutulan akış bilgileri. */
    private const DURUM = 'kimlik_durum';

    private const DOGRULAYICI = 'kimlik_dogrulayici';

    public function boot(): void
    {
        /*
         * Rotalar `guest` altında: oturumu olan birinin yeniden giriş akışına
         * girmesi anlamsız. Kapalı kurulumda uçlar 404 döner, yani
         * yapılandırılmamış bir kurulum yarım çalışmaz.
         */
        Route::middleware(['web', 'guest'])->group(function (): void {
            Route::get('giris/merkez', fn () => $this->baslat())->name('kimlik.giris');
            Route::get('giris/merkez/geri', fn () => $this->geri())->name('kimlik.donus');
        });

        /* Giriş ekranında tek satır: @kimlikGiris */
        Blade::directive('kimlikGiris', fn (): string => '<?php echo \App\KimlikMerkezi::dugme(); ?>');
    }

    /* ── Yapılandırma ── */

    public static function acikMi(): bool
    {
        return (bool) env('KIMLIK_ACIK', false) && filled(self::sir()) && filled(self::istemci());
    }

    private static function adres(): string
    {
        return rtrim((string) env('KIMLIK_ADRES', self::ADRES), '/');
    }

    private static function istemci(): string
    {
        return (string) env('KIMLIK_ISTEMCI', self::ISTEMCI);
    }

    private static function sir(): string
    {
        return (string) env('KIMLIK_ISTEMCI_SIR', '');
    }

    private static function donus(): string
    {
        return (string) env('KIMLIK_DONUS', self::DONUS);
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
    public static function dugme(): string
    {
        if (! self::acikMi()) {
            return '';
        }

        $adres = route('kimlik.giris');
        $ad = e((string) env('KIMLIK_AD', self::AD));

        return <<<HTML
        <style>
        .kimlik-dugme{display:flex;align-items:center;justify-content:center;gap:.5rem;
        width:100%;min-height:2.25rem;padding:0 .75rem;border:1px solid currentColor;
        border-radius:4px;color:inherit;font:inherit;font-weight:600;text-decoration:none;
        opacity:.92;transition:opacity 90ms ease}
        .kimlik-dugme:hover{opacity:1}
        .kimlik-ayrac{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;
        gap:.5rem;margin:1rem 0;font-size:.75rem;opacity:.6}
        .kimlik-ayrac::before,.kimlik-ayrac::after{content:'';height:1px;background:currentColor;opacity:.35}
        </style>
        <a href="{$adres}" class="kimlik-dugme">{$ad} ile giriş</a>
        <div class="kimlik-ayrac"><span>veya</span></div>
        HTML;
    }

    /* ── Akış ── */

    private function baslat()
    {
        abort_unless(self::acikMi(), 404);

        /* RFC 7636: 43-128 karakter, URL-güvenli. */
        $dogrulayici = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $durum = Str::random(40);

        /* İkisi de OTURUMDA kalır: geri dönen isteğin bizim akışımız olduğunu böyle biliriz. */
        session()->put(self::DOGRULAYICI, $dogrulayici);
        session()->put(self::DURUM, $durum);

        $meydanOkuma = rtrim(strtr(base64_encode(hash('sha256', $dogrulayici, true)), '+/', '-_'), '=');

        return redirect()->away(self::adres().'/oauth/authorize?'.http_build_query([
            'client_id' => self::istemci(),
            'redirect_uri' => self::donus(),
            'response_type' => 'code',
            'scope' => '',
            'state' => $durum,
            'code_challenge' => $meydanOkuma,
            'code_challenge_method' => 'S256',
        ]));
    }

    private function geri()
    {
        abort_unless(self::acikMi(), 404);

        $beklenen = session()->pull(self::DURUM);
        $dogrulayici = session()->pull(self::DOGRULAYICI);

        /*
         * DURUM EŞLEŞMELİ (CSRF).
         *
         * Eşleşmiyorsa bu akışı biz başlatmamışız demektir: saldırgan, kendi
         * kodunu kurbanın tarayıcısında kullandırıp onu kendi hesabına
         * bağlayabilirdi.
         */
        if (blank($beklenen) || ! hash_equals((string) $beklenen, (string) request()->query('state'))) {
            return $this->hata('Giriş akışı doğrulanamadı; lütfen yeniden deneyin.');
        }

        if (blank(request()->query('code')) || blank($dogrulayici)) {
            return $this->hata('Merkezden kod gelmedi.');
        }

        try {
            $jeton = $this->istek()->asForm()->post(self::adres().'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => self::istemci(),
                'client_secret' => self::sir(),
                'redirect_uri' => self::donus(),
                'code_verifier' => (string) $dogrulayici,
                'code' => (string) request()->query('code'),
            ])->throw()->json();

            $kimlik = $this->istek()
                ->withToken((string) ($jeton['access_token'] ?? ''))
                ->acceptJson()
                ->get(self::adres().'/oauth/kullanici')
                ->throw()
                ->json();
        } catch (Throwable $e) {
            report($e);

            return $this->hata('Merkezle bağlantı kurulamadı; lütfen parolanızla girin.');
        }

        $kullanici = $this->esles($kimlik);

        /*
         * Eşleşme yoksa VARSAYILAN OLARAK hesap açılmaz.
         *
         * Merkezde hesabı olmak, bu projede işi olmak demek değildir. Hesabı
         * projenin yöneticisi açar.
         *
         * KIMLIK_OTOMATIK_HESAP=true ise hesap açılır ama ETKİN DEĞİL: proje
         * kendi "beklemede" alanlarını KIMLIK_YENI_HESAP ile söyler ve
         * yöneticisi onaylayana kadar kimse içeri giremez. Böylece yönetici
         * ad/e-posta yazmak yerine yalnızca onaylıyor.
         */
        if (! $kullanici) {
            $kullanici = $this->hesapAc($kimlik);
        }

        if (! $kullanici) {
            return $this->hata('Bu kimlikle eşleşen bir hesap yok. Hesap açma isteği için sistem yöneticinize başvurun.');
        }

        Auth::login($kullanici, true);
        session()->regenerate();

        return redirect()->intended($this->anasayfa());
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
    private function esles(array $kimlik)
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

        return $this->etkinMi($kullanici) ? $kullanici : null;
    }

    /**
     * Eşleşmeyen kimlik için hesap açar.
     *
     * Kapalıysa (varsayılan) null döner ve giriş reddedilir.
     *
     * Açıkken hesabın NASIL doğacağını proje söyler; tahmin edilmez:
     *
     *  - KIMLIK_ROL_ESLEME verilmişse hesap ETKİN doğar ve merkezdeki rolün
     *    karşılığını alır. Onay zaten merkezde verilmiştir: kişi orada
     *    onaylanmış ve kendisine bir rol atanmıştır.
     *  - KIMLIK_YENI_HESAP verilmişse o alanlar yazılır; proje ikinci bir onay
     *    istiyorsa hesabı burada "beklemede" doğurur ({"status":"passive"}).
     *
     * @param  array<string, mixed>  $kimlik
     */
    private function hesapAc(array $kimlik)
    {
        if (! (bool) env('KIMLIK_OTOMATIK_HESAP', false)) {
            return null;
        }

        $alanlar = json_decode((string) env('KIMLIK_YENI_HESAP', ''), true);
        $alanlar = is_array($alanlar) ? $alanlar : [];

        $rol = $this->rolAlani($kimlik);

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
                'KIMLIK_OTOMATIK_HESAP açık ama ne KIMLIK_ROL_ESLEME ne KIMLIK_YENI_HESAP yazılmış; hesap açılmadı.'
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
        return $this->etkinMi($kullanici) ? $kullanici : null;
    }

    /**
     * Merkezdeki rolün projedeki karşılığı.
     *
     * İSTEĞE BAĞLIDIR: KIMLIK_ROL_ALANI ve KIMLIK_ROL_ESLEME verilmemişse boş
     * dizi döner ve hesap yalnızca KIMLIK_YENI_HESAP alanlarıyla açılır.
     *
     * Verilmişse ROL ŞART OLUR: merkezde rolü olmayan ya da eşlemede karşılığı
     * bulunmayan kimlik için `false` döner ve hesap açılmaz. Karşılığı
     * olmayan bir rolü varsayılana çevirmek, kimin ne yetkiyle içeri girdiğini
     * belirsizleştirirdi.
     *
     * @param  array<string, mixed>  $kimlik
     * @return array<string, mixed>|false
     */
    private function rolAlani(array $kimlik): array|false
    {
        $alan = (string) env('KIMLIK_ROL_ALANI', '');
        $esleme = json_decode((string) env('KIMLIK_ROL_ESLEME', ''), true);

        if ($alan === '' || ! is_array($esleme) || $esleme === []) {
            return [];
        }

        $slug = (string) ($kimlik['rol'] ?? '');

        if ($slug === '' || ! array_key_exists($slug, $esleme)) {
            report(new \RuntimeException(
                "Merkezdeki rol '{$slug}' KIMLIK_ROL_ESLEME içinde yok; hesap açılmadı."
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
    private function etkinMi($kullanici): bool
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
    private function istek()
    {
        $istek = Http::timeout(15);
        $kok = env('KIMLIK_KOK');

        return filled($kok) ? $istek->withOptions(['verify' => $kok]) : $istek;
    }

    /** Giriş sonrası gidilecek yer; proje kendi rotasını verebilir. */
    private function anasayfa(): string
    {
        $rota = (string) env('KIMLIK_ANASAYFA', '');

        return $rota !== '' && Route::has($rota) ? route($rota) : '/';
    }

    private function hata(string $mesaj)
    {
        $giris = Route::has('login') ? route('login') : '/';

        return redirect()->to($giris)->withErrors(['email' => $mesaj]);
    }
}
PHP;
    }
}
