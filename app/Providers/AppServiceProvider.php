<?php

namespace App\Providers;

use App\Models\OauthIstemci;
use App\Models\User;
use App\Services\OrtakCikis;
use App\Services\Posta\PostaAyarlari;
use App\Services\SaatDilimi;
use App\Services\Yetki\YetkiCozumleyici;
use App\Services\Yetki\YetkiSozlugu;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Saat dilimi ayardan gelir. Ayar tablosu henüz yoksa (ilk kurulum,
         * göç sırası) sessizce atlanır ve APP_TIMEZONE geçerli kalır.
         */
        rescue(fn () => SaatDilimi::uygula(), null, false);

        /*
         * ORTAK ÇIKIŞ: oturumun açıldığı an. Kişinin son çıkışından önce
         * açılmış oturum kapanır (bkz. OrtakCikis, OrtakCikisSonrasiKapat).
         * Olay her giriş yolunda gelir: parola, ikinci adım, beni hatırla.
         */
        Event::listen(Login::class, function (): void {
            if (request()->hasSession()) {
                request()->session()->put(OrtakCikis::OTURUM_ANAHTARI, OrtakCikis::simdiMs());
            }
        });

        /*
         * YETKİLER TEK YERDEN TANIMLANIR.
         *
         * config/permissions.php'deki her "<kaynak>.<aksiyon>" bir Gate
         * yeteneğine dönüşür. Yeni bir yetki eklemek için o dosyaya bir satır
         * yazmak yeterli; controller, Blade ve rol ekranı kendiliğinden uyar.
         */
        foreach ($this->app->make(YetkiSozlugu::class)->yetenekler() as $yetenek) {
            Gate::define($yetenek, fn (User $kullanici): bool => $this->app
                ->make(YetkiCozumleyici::class)
                ->yapabilirMi($kullanici, $yetenek));
        }

        /*
         * "Yönetim" tek bir yetki DEĞİL, bir kapıdır: en az bir yönetim
         * yetkisi olan içeri girer, ne göreceğini yetkileri belirler.
         * Menüdeki bağlantı da bunu sorar.
         */
        Gate::define('yonet', fn (User $kullanici): bool => $this->app
            ->make(YetkiCozumleyici::class)
            ->yonetimeGirebilirMi($kullanici));

        /*
         * OAuth YÜZEYİ DAR TUTULUR.
         *
         * Yalnızca Authorization Code + PKCE kullanılıyor. Diğer akışlar
         * kapalı: parola akışı istemciye kullanıcının parolasını verdirir,
         * implicit akış jetonu adres çubuğuna yazar, istemci kimlik bilgisi
         * akışı ise kullanıcı kimliği taşımaz. Hiçbirine ihtiyacımız yok ve
         * açık duran her akış ayrı bir saldırı yüzeyi.
         */
        Passport::enablePasswordGrant(false);

        /*
         * Jeton ömürleri KISA: bize kimlik lazım, uzun ömürlü API erişimi
         * değil. Uzun ömürlü bir jeton, çalındığında uzun süre geçerli olur.
         */
        Passport::tokensExpireIn(now()->addMinutes((int) config('kimlik.jeton_dakika')));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('kimlik.yenileme_gun')));

        Passport::authorizationView(fn ($parametreler) => view('oauth.onay', $parametreler));

        /*
         * KENDİ projelerimizde onay ekranı sorulmaz.
         *
         * Onay, üçüncü tarafa veri verirken anlamlıdır. Buradaki istemciler
         * bizim projelerimiz; her girişte "izin veriyor musunuz" sormak
         * kullanıcıyı okumadan tıklamaya alıştırır — ve asıl önemli bir onay
         * çıktığında o da tıklanıp geçilir.
         *
         * Ayrım kaydın kendisinde: bir istemci bir kullanıcı adına
         * açıldıysa (üçüncü taraf) onay yine sorulur.
         */
        /*
         * Sayfalama PANELİN kendi şeridiyle çizilir.
         *
         * Laravel'in varsayılan görünümü Tailwind sınıfları basar; bu panelde
         * Tailwind yok ve şerit biçimsiz, devasa bir liste olarak çıkardı.
         * Biçem pagination.css'te, görünüm burada bağlanır.
         */
        Paginator::defaultView('vendor.pagination.default');

        Passport::useClientModel(OauthIstemci::class);

        /*
         * SMTP ayarları ekrandan gelir — ama ANCAK POSTA KULLANILDIĞINDA.
         *
         * `boot()` içinde okunsaydı her istek, hiç posta göndermeyecek olanlar
         * da dahil, veritabanına uğrardı; üstelik tablo henüz oluşmamışken
         * (kurulum, göç) uygulama açılmazdı. Bu kanca yalnızca posta katmanı
         * ilk kez çözümlendiğinde çalışır.
         */
        $this->app->resolving('mail.manager', function (): void {
            $this->app->make(PostaAyarlari::class)->uygula();
        });
    }
}
