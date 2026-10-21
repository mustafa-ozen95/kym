<?php

declare(strict_types=1);

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\TwoStepController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPermissionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\Oauth\DiscoveryController;
use App\Http\Controllers\Oauth\UserInfoController;
use App\Http\Controllers\QuickAccessController;
use App\Support\RequestLimit;
use Illuminate\Support\Facades\Route;

/*
 * Hızlı Erişim: sistemlerin kapısı.
 *
 * Giriş istemez — bilinçli karar (bkz. QuickAccessController). Kimin neyi
 * göreceği kutucuk ve salon düzeyinde ayarlanır; sayfanın tamamını oturum
 * arkasına alan bir ayar yoktur, gerekirse buraya `auth` eklenir.
 */
Route::get('/', QuickAccessController::class)->name('hizli-erisim');

/*
 * Salonun kendi sayfası.
 *
 * Ayrı sayfa olarak işaretlenen salonlar ana sayfaya karışmaz; buraya bir
 * kutucukla gidilir. Şifre sormaz — görünürlüğü kutucuklarınkiyle aynı üç
 * düzeyde ve yetkisi olmayan 404 alır (403 "böyle bir sayfa var" derdi).
 */
Route::get('salon/{grup:anahtar}', [QuickAccessController::class, 'salon'])->name('salon');

/*
 * Giriş ve kayıt PENCEREDEN yapılır ama uçları normal POST'tur: betik
 * yüklenmese de çalışır. Sınırlar ayardan gelir (bkz. config/limits.php).
 *
 * Sınır SIFIRSA ara katman hiç eklenmez; karar RequestLimit'nde durur.
 * Doğrudan `'throttle:'.config(...)` yazmak sıfırı "dakikada sıfır istek"e
 * çeviriyordu, yani sınırı kaldırmak isteyen kişi ucu tümüyle kapatırdı.
 */
Route::middleware('guest')->group(function (): void {
    Route::post('giris', [LoginController::class, 'store'])
        ->middleware(RequestLimit::rotaIcin('giris'))
        ->name('giris');

    /* İkinci adım: oturum HENÜZ açılmadan, oturumda bekleyen kimlikle. */
    Route::post('giris/dogrulama', [LoginController::class, 'dogrula'])
        ->middleware(RequestLimit::rotaIcin('giris'))
        ->name('giris.dogrulama');

    Route::post('kayit', [RegistrationController::class, 'store'])
        ->middleware(RequestLimit::rotaIcin('kayit'))
        ->name('kayit');

    /*
     * Yeni bağlantı isteği. Oturumsuz — doğrulanmamış kullanıcı giriş
     * yapamıyor; oturum şartı konsaydı kilitli kalırdı.
     */
    Route::post('eposta/dogrulama', [EmailVerificationController::class, 'yenidenGonder'])
        ->name('dogrulama.gonder');
});

/*
 * Doğrulama bağlantısı.
 *
 * `guest` grubunun DIŞINDA: oturumu açık bir kullanıcı da kendi adresini
 * doğrulayabilmeli (yönetici zorunluluğu sonradan açtığında içeride olan
 * herkes bu durumda). `guest` altında olsaydı bağlantı onlar için 302 dönerdi.
 *
 * Adı `verification.verify` olmak ZORUNDA: imzalı adresi üreten Laravel'in
 * kendi bildirimi bu adı arıyor.
 */
Route::get('eposta/dogrula/{id}/{hash}', [EmailVerificationController::class, 'dogrula'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
 * Hesap ayarları: herkes yalnızca KENDİ hesabını yönetir.
 */
Route::middleware('auth')->prefix('hesap')->name('hesap.')->group(function (): void {
    Route::get('profil', [ProfileController::class, 'index'])->name('profil');
    Route::put('profil', [ProfileController::class, 'update'])->name('profil.kaydet');
    Route::put('parola', [ProfileController::class, 'parola'])->name('parola');

    Route::get('iki-adim', [TwoStepController::class, 'index'])->name('iki-adim');
    Route::post('iki-adim', [TwoStepController::class, 'store'])->name('iki-adim.baslat');
    Route::post('iki-adim/onay', [TwoStepController::class, 'onayla'])->name('iki-adim.onay');
    Route::delete('iki-adim', [TwoStepController::class, 'destroy'])->name('iki-adim.kapat');
});

Route::post('cikis', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('cikis');

/*
 * Yönetim.
 *
 * İKİ KAT: buradaki `can:yonet` yalnızca KAPIDIR — en az bir yönetim yetkisi
 * olan içeri girer. Ne yapabileceğini controller'lardaki tek tek yetkiler
 * belirler (`can:sistem.duzenle`, `can:kullanici.onayla`...).
 *
 * Kapının ayrı durması, yetkisi olmayan birine 403 yerine baştan kapalı bir
 * bölüm göstermek için. Yetkiler controller'da durur ki yeni bir uç
 * eklendiğinde grubun izni ona kendiliğinden yetmesin.
 */
Route::middleware(['auth', 'can:yonet'])->prefix('yonetim')->name('yonetim.')->group(function (): void {
    Route::get('sistemler', [SystemController::class, 'index'])->name('sistemler');
    Route::post('sistemler', [SystemController::class, 'store'])->name('sistemler.ekle');
    /* Sıra ucu {sistem} rotasından ÖNCE: yoksa "sira" bir uuid sanılırdı. */
    Route::put('sistemler/sira/{grup:anahtar}', [SystemController::class, 'sirala'])->name('sistemler.sirala');
    Route::put('sistemler/{sistem}', [SystemController::class, 'update'])->name('sistemler.guncelle');
    Route::delete('sistemler/{sistem}', [SystemController::class, 'destroy'])->name('sistemler.sil');

    /* Salonlar: kutucukların dizildiği bölümler; bazıları kendi sayfası. */
    Route::get('salonlar', [GroupController::class, 'index'])->name('gruplar');
    Route::post('salonlar', [GroupController::class, 'store'])->name('gruplar.ekle');
    Route::put('salonlar/sira', [GroupController::class, 'sirala'])->name('gruplar.sirala');
    Route::put('salonlar/{grup}', [GroupController::class, 'update'])->name('gruplar.guncelle');
    Route::delete('salonlar/{grup}', [GroupController::class, 'destroy'])->name('gruplar.sil');

    Route::get('ayarlar', [SettingController::class, 'index'])->name('ayarlar');
    Route::put('ayarlar', [SettingController::class, 'update'])->name('ayarlar.kaydet');
    Route::put('ayarlar/posta', [SettingController::class, 'posta'])->name('ayarlar.posta');

    /* Deneme iletisi dışarıya çıkar; sınırlı, çünkü her basış gerçek posta gönderir. */
    Route::post('ayarlar/posta/deneme', [SettingController::class, 'postaSina'])
        ->middleware('throttle:5,1')
        ->name('ayarlar.posta.deneme');

    Route::get('kullanicilar', [UserController::class, 'index'])->name('kullanicilar');
    Route::post('kullanicilar', [UserController::class, 'store'])->name('kullanicilar.ekle');
    Route::put('kullanicilar/{user}', [UserController::class, 'update'])->name('kullanicilar.guncelle');
    Route::delete('kullanicilar/{user}', [UserController::class, 'destroy'])->name('kullanicilar.sil');

    /* Kişiye özel istisnalar: rolün şablonunun üstüne yazar. */
    Route::get('kullanicilar/{user}/yetkiler', [UserPermissionController::class, 'show'])
        ->name('kullanicilar.yetkiler');
    Route::put('kullanicilar/{user}/yetkiler', [UserPermissionController::class, 'update'])
        ->name('kullanicilar.yetkiler.kaydet');

    /*
     * Bağlı uygulamalar: yeni bir siteyi merkeze bağlamanın TEK yeri.
     *
     * "Sil" yok, "kaldır" var: kayıt silinirse denetim kaydındaki "şu
     * uygulama şu kullanıcıyı sordu" satırları sahipsiz kalır.
     */
    Route::get('uygulamalar', [ClientController::class, 'index'])->name('istemciler');
    Route::post('uygulamalar', [ClientController::class, 'store'])->name('istemciler.ekle');

    /* Projeye konacak tek dosya; sır İÇİNDE DEĞİL, .env'den okunur. */
    Route::get('uygulamalar/{istemci}/kurulum', [ClientController::class, 'kurulumDosyasi'])
        ->name('istemciler.kurulum');
    Route::put('uygulamalar/{istemci}', [ClientController::class, 'update'])->name('istemciler.guncelle');
    Route::put('uygulamalar/{istemci}/sir', [ClientController::class, 'sirYenile'])->name('istemciler.sir');
    Route::delete('uygulamalar/{istemci}', [ClientController::class, 'destroy'])->name('istemciler.kaldir');
    Route::delete('uygulamalar/{istemci}/kalici', [ClientController::class, 'kaliciSil'])->name('istemciler.kalici-sil');

    /*
     * Roller, Ayarlar'ın bir SEKMESİDİR (bkz. SettingSections): kullanıcı onu
     * Ayarlar'ın altında arar.
     */
    /*
     * Denetim kaydı da Ayarlar'ın bir sekmesi. SİLME UCU YOK ve olmayacak:
     * silinebilen bir denetim kaydı, denetim kaydı değildir.
     */
    /*
     * SERTİFİKA. Tur sunucuda koşar; panel isteği bırakır ve sonucu okur
     * (bkz. CertificateController).
     */
    Route::get('ayarlar/sertifika', [CertificateController::class, 'index'])->name('sertifika');
    Route::post('ayarlar/sertifika/tur', [CertificateController::class, 'baslat'])->name('sertifika.baslat');
    Route::post('ayarlar/sertifika/onayla', [CertificateController::class, 'tamamla'])->name('sertifika.tamamla');

    /*
     * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır; kopya
     * yeniden başlatmada, merkez kapalıyken yapılır (bkz. DatabaseController
     * ve ConnectionFile).
     */
    Route::get('ayarlar/veritabani', [DatabaseController::class, 'index'])->name('veritabani');
    Route::post('ayarlar/veritabani/sina', [DatabaseController::class, 'sina'])->name('veritabani.sina');
    Route::post('ayarlar/veritabani/tasi', [DatabaseController::class, 'tasi'])->name('veritabani.tasi');

    /*
     * GÜNCELLEME. Yalnızca bakar: çalışan sürüm GitHub'daki son başarılı
     * derlemeyle karşılaştırılır (bkz. UpdateCheck).
     */
    Route::get('ayarlar/guncelleme', [UpdateController::class, 'index'])->name('guncelleme');
    Route::post('ayarlar/guncelleme', [UpdateController::class, 'denetle'])->name('guncelleme.denetle');

    Route::get('ayarlar/kayitlar', [AuditController::class, 'index'])->name('kayitlar');
    Route::post('ayarlar/kayitlar/dogrula', [AuditController::class, 'dogrula'])->name('kayitlar.dogrula');

    Route::get('ayarlar/roller', [RoleController::class, 'index'])->name('roller');
    Route::post('ayarlar/roller', [RoleController::class, 'store'])->name('roller.ekle');
    Route::get('ayarlar/roller/{rol}', [RoleController::class, 'show'])->name('roller.goster');
    Route::put('ayarlar/roller/{rol}', [RoleController::class, 'update'])->name('roller.guncelle');
    Route::delete('ayarlar/roller/{rol}', [RoleController::class, 'destroy'])->name('roller.sil');
});

/*
 * KAPI — korunan sayfanın bekçisi.
 *
 * `auth` ara katmanının DIŞINDA ve bilinçli: o katman kimliksiz isteği giriş
 * sayfasına yönlendirir, nginx `auth_request` ise 2xx/401/403 dışındaki her
 * yanıtı 500 sayar. Kimlik kontrolü controller içinde yapılır ve 401 döner.
 */
Route::middleware('web')->get('kapi/{anahtar}', GateController::class)->name('kapi');

/*
 * Kimlik uçları.
 *
 * /oauth/authorize ve /oauth/token Passport'tan gelir. Buradakiler ikisini
 * tamamlar: istemci kurulumu için keşif belgesi, giriş sonrası kimliği okumak
 * için kullanıcı ucu.
 */
Route::get('.well-known/openid-configuration', DiscoveryController::class)->name('kimlik.kesif');
Route::get('oauth/kullanici', UserInfoController::class)->name('kimlik.kullanici');
