<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientRequest;
use App\Models\OauthClient;
use App\Models\Role;
use App\Services\ActivityRecorder;
use App\Services\Identity\IntegrationGuide;
use App\Services\Identity\SetupFile;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;

/**
 * Merkeze bağlı uygulamalar.
 *
 * Yeni bir siteyi bağlamak, o siteye "bu kullanıcı kim" sorusunu sorma hakkı
 * vermektir. Bu yüzden İSTEMCİLER ELLE KAYDEDİLİR: dinamik kayıt açık olsaydı
 * adresi bilen herkes kendini istemci yapıp kullanıcıları kendi sayfasına
 * yönlendirebilir ve "Kimlik Yönetim Merkezi ile giriş" görüntüsü altında kimlik
 * toplayabilirdi.
 *
 * DÖNÜŞ ADRESİ TAM YAZILIR. Passport yalnızca birebir eşleşeni kabul eder ve
 * kodun başka bir yere gönderilmesini engelleyen asıl koruma budur; bu yüzden
 * form da yalnızca https kabul eder.
 *
 * SIR BİR KEZ GÖSTERİLİR. Saklanmaz; kaybolursa yenilenir ve yenileme o
 * uygulamanın girişini o an durdurur — bu yüzden ayrı bir yetki ister.
 */
class ClientController extends Controller implements HasMiddleware
{
    public function __construct(
        private IntegrationGuide $rehber,
        private SetupFile $kurulum,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:istemci.gor'], only: ['index', 'setupFile']),
            new Middleware(['auth', 'can:istemci.ekle'], only: ['store']),
            new Middleware(['auth', 'can:istemci.duzenle'], only: ['update']),
            new Middleware(['auth', 'can:istemci.sir'], only: ['regenerateSecret']),
            /* Kalıcı silme de bir sil eylemidir: kapı (can:yonet) tek başına yetmez. */
            new Middleware(['auth', 'can:istemci.sil'], only: ['destroy', 'purge']),
        ];
    }

    public function index(Request $request, #[CurrentUser] $kullanici): View
    {
        $istemciler = OauthClient::with('roller')->orderBy('name')->get();

        return view('admin.clients', [
            'istemciler' => $istemciler,

            /*
             * Erişim kapısı ekranda kurulur: hangi rol hangi uygulamaya
             * girebilir. Değerler uuid taşır; sıralı kimlik dışarı çıkmaz.
             */
            'roller' => Role::sirali()->get(),

            /*
             * Her uygulamanın kendi .env bloğu HAZIR üretilir: elle yazılan
             * bir örnek, ilk adres değişikliğinde yanlış talimat vermeye başlar.
             */
            'envBloklari' => $istemciler->mapWithKeys(
                fn (OauthClient $i): array => [$i->getKey() => $this->rehber->envBlogu($i)],
            )->all(),

            'adimlar' => $this->rehber->laravelAdimlari(),
            'kesifAdresi' => $this->rehber->kesifAdresi(),

            /*
             * Protokol ve Node örneği de ekranda: merkez standart OAuth2 +
             * OIDC keşfi konuşuyor, yani PHP dışındaki projeler de kendi
             * kütüphaneleriyle bağlanabilir. Bunu yazmamak, "bizim dosyamız
             * yoksa bağlanamazsın" izlenimi verirdi.
             */
            'protokoller' => $istemciler->mapWithKeys(
                fn (OauthClient $i): array => [$i->getKey() => $this->rehber->protokol($i)],
            )->all(),
            'nodeOrnekleri' => $istemciler->mapWithKeys(
                fn (OauthClient $i): array => [$i->getKey() => $this->rehber->nodeOrnegi($i)],
            )->all(),

            'ekleyebilir' => $kullanici->can('istemci.ekle'),
            'duzenleyebilir' => $kullanici->can('istemci.duzenle'),
            'sirYenileyebilir' => $kullanici->can('istemci.sir'),
            'silebilir' => $kullanici->can('istemci.sil'),

            /*
             * Pencerenin ADRESİ vardır: doğrulama hatasında sayfa aynı adrese
             * döndüğü için pencere açık geri gelir ve kullanıcı formunu
             * yeniden açmak zorunda kalmaz.
             */
            'window' => $request->query('window') === 'add' ? 'add' : null,
        ]);
    }

    /**
     * Projeye konacak TEK dosya.
     *
     * SIR İÇİNDE DEĞİL: dosya kaynak denetimine girebilir ve tarayıcının
     * indirilenler klasöründe kalır. Sır .env'den okunur; dosya yalnızca
     * yerinin nereye yazılacağını söyler.
     */
    public function setupFile(OauthClient $client): Response
    {
        return response($this->kurulum->icerik($client))
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="'.SetupFile::FILE_NAME.'"');
    }

    public function store(
        ClientRequest $request,
        ClientRepository $istemciler,
        ActivityRecorder $recorder,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        $veri = $request->validated();

        $client = $istemciler->createAuthorizationCodeGrantClient($veri['name'], $request->adresler());

        /*
         * BİLDİRİM SIRRI kayıt anında üretilir.
         *
         * OAuth sırrından ayrıdır ve DÜZ METİN saklanır: merkez bununla
         * "bu kimlik değişti" bildirimini imzalıyor, yani üretebilmesi
         * gerekiyor. OAuth sırrı bcrypt özeti olduğu için bu işe yaramaz.
         *
         * Hiçbir yetki taşımaz: bildirimin gövdesine zaten güvenilmiyor,
         * proje doğruyu merkezden kendi soruyor.
         */
        $client->forceFill(['bildirim_sirri' => Str::random(48)])->save();

        $recorder->record('istemci-eklendi', $veri['name'].' istemci olarak kaydedildi', $kullanici);

        /*
         * Sır oturuma DEĞİL, tek seferlik flash'a konur: sayfa yenilenince
         * kaybolur. Kalıcı olsaydı ekranı açık unutan biri onu paylaşmış olurdu.
         */
        /* Temiz adrese dönülür; yoksa "yeni uygulama bağla" penceresi kaydettikten sonra açık kalırdı. */
        return redirect()->route('admin.clients')->with([
            'status' => $veri['name'].' bağlandı. Sır aşağıda BİR KEZ görünüyor.',
            'yeniIstemci' => $client->getKey(),
            'yeniSir' => $client->plainSecret,
            'yeniEnv' => $this->rehber->envBlogu($client, $client->plainSecret),
        ]);
    }

    public function update(
        ClientRequest $request,
        OauthClient $client,
        ActivityRecorder $recorder,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        $oncekiRoller = $client->roller()->orderBy('slug')->pluck('slug')->implode(',') ?: 'herkes';
        $oncekiOtomatik = (bool) $client->otomatik_hesap;

        $client->forceFill([
            'name' => $request->validated('name'),
            'redirect_uris' => $request->adresler(),
            'otomatik_hesap' => $request->boolean('auto_account'),
        ])->save();

        /*
         * ERİŞİM KAPISI: hangi roller bu uygulamaya girebilir.
         *
         * Rol uuid'leriyle gelir, iç kimliğe burada çevrilir. Boş liste
         * kapıyı herkese açar (bkz. OauthClient::erisebilirMi).
         */
        $client->roller()->sync(
            Role::whereIn('uuid', (array) $request->validated('roles', []))->pluck('id')->all(),
        );

        $yeniRoller = $client->roller()->orderBy('slug')->pluck('slug')->implode(',') ?: 'herkes';

        /*
         * NE DEĞİŞTİĞİ yazılır: bir uygulamaya hangi rolün ne zaman kimin
         * eliyle erişebildiği denetimde sorulacak sorulardan biridir.
         */
        $degisenler = array_filter([
            $oncekiRoller !== $yeniRoller ? "girebilen roller {$oncekiRoller} → {$yeniRoller}" : null,
            $oncekiOtomatik !== (bool) $client->otomatik_hesap
                ? 'otomatik hesap '.($client->otomatik_hesap ? 'açıldı' : 'kapatıldı')
                : null,
        ]);

        $recorder->record(
            'istemci-guncellendi',
            $client->name.' güncellendi'.($degisenler === [] ? '' : ' · '.implode(', ', $degisenler)),
            $kullanici,
        );

        return redirect()->route('admin.clients')->with('status', $client->name.' güncellendi.');
    }

    /**
     * Sırrı yeniler.
     *
     * Bu işlem uygulamanın girişini O AN durdurur: eski sır geçersiz olur ve
     * jeton takası başarısızlaşır. Kullanıcılar parolayla girmeye devam eder
     * (merkez ile giriş ikinci yoldur), ama yeni sır projeye yazılana kadar
     * merkez düğmesi çalışmaz.
     */
    public function regenerateSecret(
        OauthClient $client,
        ActivityRecorder $recorder,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        $yeni = Str::random(40);

        /*
         * Sır yenilenirken ESKİ JETONLAR da düşer.
         *
         * Yalnızca sırrı değiştirmek, o uygulamanın elindeki geçerli jetonları
         * dokunulmadan bırakırdı: sır sızdığı için yeniliyorsak, sızıntıyla
         * alınmış jeton ömrü dolana kadar çalışmaya devam ederdi.
         */
        $client->forceFill(['secret' => $yeni])->save();
        $this->jetonlariDusur($client);

        $recorder->record('istemci-sir-yenilendi', $client->name.' sırrı yenilendi', $kullanici);

        return back()->with([
            'status' => $client->name.' sırrı yenilendi. Yeni sırrı projeye yazana kadar merkez ile giriş çalışmaz.',
            'yeniIstemci' => $client->getKey(),
            'yeniSir' => $yeni,
            'yeniEnv' => $this->rehber->envBlogu($client, $yeni),
        ]);
    }

    public function destroy(OauthClient $client, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $ad = $client->name;

        /*
         * SİLİNMEZ, İPTAL EDİLİR.
         *
         * Kaydı silmek, o istemci adına verilmiş jetonları sahipsiz bırakır ve
         * denetim kaydındaki "şu uygulama şu kullanıcıyı sordu" satırları
         * hangi uygulamaya ait olduğunu kaybeder. İptal edilen istemci hiçbir
         * akışı tamamlayamaz; geçmişi ise durur.
         */
        $client->forceFill(['revoked' => true])->save();
        $this->jetonlariDusur($client);

        $recorder->record('istemci-iptal', $ad.' bağlantısı kaldırıldı', $kullanici);

        return back()->with('status', $ad.' bağlantısı kaldırıldı; artık merkez ile giriş yapamaz.');
    }

    /**
     * İptal edilmiş bir istemciyi KALICI olarak siler.
     *
     * İki adımlıdır: önce bağlantı kaldırılır (iptal), sonra istenirse
     * kayıt silinir. Tek adımda silinseydi yanlışlıkla silmenin geri dönüşü
     * olmazdı; iptal edilmiş bir kaydı silmek ise bilinçli bir ikinci karardır.
     *
     * Denetim kaydı bundan etkilenmez: activity_logs istemciye yabancı
     * anahtarla bağlı değildir, uygulamanın adını METİN olarak taşır. Asıl
     * bağ jeton tablolarındadır; onlar önce temizlenir, yoksa silinen
     * istemciye ait satırlar sahipsiz kalırdı.
     */
    public function purge(OauthClient $client, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        if (! $client->revoked) {
            return back()->withErrors([
                'istemci' => 'Önce bağlantıyı kaldırın; kalıcı silme yalnızca kaldırılmış uygulamalar içindir.',
            ]);
        }

        $ad = $client->name;

        $this->jetonlariDusur($client);
        $client->tokens()->delete();
        DB::table('oauth_auth_codes')->where('client_id', $client->getKey())->delete();
        DB::table('oauth_device_codes')->where('client_id', $client->getKey())->delete();
        $client->delete();

        $recorder->record('istemci-silindi', $ad.' kaydı kalıcı olarak silindi', $kullanici);

        return back()->with('status', $ad.' kaydı kalıcı olarak silindi.');
    }

    /**
     * İstemcinin verilmiş jetonlarını iptal eder.
     *
     * Bunsuz "bağlantıyı kaldır" ve "sırrı yenile" ancak jetonların ömrü
     * dolduğunda etkili olurdu; ikisi de tam olarak acele edilen durumlarda
     * kullanılıyor.
     */
    private function jetonlariDusur(OauthClient $client): void
    {
        $client->tokens()->with('refreshToken')->each(function (Token $jeton): void {
            $jeton->refreshToken?->revoke();
            $jeton->revoke();
        });
    }
}
