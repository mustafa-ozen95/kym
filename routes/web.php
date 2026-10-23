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
Route::get('/', QuickAccessController::class)->name('quick-access');

/*
 * Salonun kendi sayfası.
 *
 * Ayrı sayfa olarak işaretlenen salonlar ana sayfaya karışmaz; buraya bir
 * kutucukla gidilir. Şifre sormaz — görünürlüğü kutucuklarınkiyle aynı üç
 * düzeyde ve yetkisi olmayan 404 alır (403 "böyle bir sayfa var" derdi).
 */
Route::get('group/{group:anahtar}', [QuickAccessController::class, 'group'])->name('group');

/*
 * Giriş ve kayıt PENCEREDEN yapılır ama uçları normal POST'tur: betik
 * yüklenmese de çalışır. Sınırlar ayardan gelir (bkz. config/limits.php).
 *
 * Sınır SIFIRSA ara katman hiç eklenmez; karar RequestLimit'nde durur.
 * Doğrudan `'throttle:'.config(...)` yazmak sıfırı "dakikada sıfır istek"e
 * çeviriyordu, yani sınırı kaldırmak isteyen kişi ucu tümüyle kapatırdı.
 */
Route::middleware('guest')->group(function (): void {
    Route::post('login', [LoginController::class, 'store'])
        ->middleware(RequestLimit::forRoute('login'))
        ->name('login');

    /* İkinci adım: oturum HENÜZ açılmadan, oturumda bekleyen kimlikle. */
    Route::post('login/verify', [LoginController::class, 'verify'])
        ->middleware(RequestLimit::forRoute('login'))
        ->name('login.verify');

    Route::post('register', [RegistrationController::class, 'store'])
        ->middleware(RequestLimit::forRoute('register'))
        ->name('register');

    /*
     * Yeni bağlantı isteği. Oturumsuz — doğrulanmamış kullanıcı giriş
     * yapamıyor; oturum şartı konsaydı kilitli kalırdı.
     */
    Route::post('email/verification', [EmailVerificationController::class, 'resend'])
        ->name('verification.resend');
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
Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
 * Hesap ayarları: herkes yalnızca KENDİ hesabını yönetir.
 */
Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'password'])->name('password');

    Route::get('two-step', [TwoStepController::class, 'index'])->name('two-step');
    Route::post('two-step', [TwoStepController::class, 'store'])->name('two-step.start');
    Route::post('two-step/confirm', [TwoStepController::class, 'confirm'])->name('two-step.confirm');
    Route::delete('two-step', [TwoStepController::class, 'destroy'])->name('two-step.disable');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

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
Route::middleware(['auth', 'can:yonet'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('systems', [SystemController::class, 'index'])->name('systems');
    Route::post('systems', [SystemController::class, 'store'])->name('systems.store');
    /* Sıra ucu {system} rotasından ÖNCE: yoksa "sira" bir uuid sanılırdı. */
    Route::put('systems/order/{group:anahtar}', [SystemController::class, 'reorder'])->name('systems.reorder');
    Route::put('systems/{system}', [SystemController::class, 'update'])->name('systems.update');
    Route::delete('systems/{system}', [SystemController::class, 'destroy'])->name('systems.destroy');

    /* Salonlar: kutucukların dizildiği bölümler; bazıları kendi sayfası. */
    Route::get('groups', [GroupController::class, 'index'])->name('groups');
    Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
    Route::put('groups/order', [GroupController::class, 'reorder'])->name('groups.reorder');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::put('settings/mail', [SettingController::class, 'mail'])->name('settings.mail');

    /* Deneme iletisi dışarıya çıkar; sınırlı, çünkü her basış gerçek posta gönderir. */
    Route::post('settings/mail/test', [SettingController::class, 'mailTest'])
        ->middleware('throttle:5,1')
        ->name('settings.mail.test');

    Route::get('users', [UserController::class, 'index'])->name('users');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    /* Kişiye özel istisnalar: rolün şablonunun üstüne yazar. */
    Route::get('users/{user}/permissions', [UserPermissionController::class, 'show'])
        ->name('users.permissions');
    Route::put('users/{user}/permissions', [UserPermissionController::class, 'update'])
        ->name('users.permissions.update');

    /*
     * Bağlı uygulamalar: yeni bir siteyi merkeze bağlamanın TEK yeri.
     *
     * "Sil" yok, "kaldır" var: kayıt silinirse denetim kaydındaki "şu
     * uygulama şu kullanıcıyı sordu" satırları sahipsiz kalır.
     */
    Route::get('clients', [ClientController::class, 'index'])->name('clients');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');

    /* Projeye konacak tek dosya; sır İÇİNDE DEĞİL, .env'den okunur. */
    Route::get('clients/{client}/setup', [ClientController::class, 'setupFile'])
        ->name('clients.setup');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::put('clients/{client}/secret', [ClientController::class, 'regenerateSecret'])->name('clients.secret');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.revoke');
    Route::delete('clients/{client}/purge', [ClientController::class, 'purge'])->name('clients.purge');

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
    Route::get('settings/certificate', [CertificateController::class, 'index'])->name('certificate');
    Route::post('settings/certificate/run', [CertificateController::class, 'run'])->name('certificate.run');
    Route::post('settings/certificate/confirm', [CertificateController::class, 'confirm'])->name('certificate.confirm');

    /*
     * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır; kopya
     * yeniden başlatmada, merkez kapalıyken yapılır (bkz. DatabaseController
     * ve ConnectionFile).
     */
    Route::get('settings/database', [DatabaseController::class, 'index'])->name('database');
    Route::post('settings/database/test', [DatabaseController::class, 'test'])->name('database.test');
    Route::post('settings/database/migrate', [DatabaseController::class, 'migrate'])->name('database.migrate');

    /*
     * GÜNCELLEME. Çalışan sürüm GitHub'daki son başarılı derlemeyle
     * karşılaştırılır (bkz. UpdateCheck); yeni sürüm varsa apply platformun
     * deploy hook'unu tetikler (bkz. UpdateApply).
     */
    Route::get('settings/update', [UpdateController::class, 'index'])->name('update');
    Route::post('settings/update', [UpdateController::class, 'check'])->name('update.check');
    Route::post('settings/update/apply', [UpdateController::class, 'apply'])->name('update.apply');

    Route::get('settings/logs', [AuditController::class, 'index'])->name('logs');
    Route::post('settings/logs/verify', [AuditController::class, 'verify'])->name('logs.verify');

    Route::get('settings/roles', [RoleController::class, 'index'])->name('roles');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('settings/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

/*
 * KAPI — korunan sayfanın bekçisi.
 *
 * `auth` ara katmanının DIŞINDA ve bilinçli: o katman kimliksiz isteği giriş
 * sayfasına yönlendirir, nginx `auth_request` ise 2xx/401/403 dışındaki her
 * yanıtı 500 sayar. Kimlik kontrolü controller içinde yapılır ve 401 döner.
 */
Route::middleware('web')->get('gate/{anahtar}', GateController::class)->name('gate');

/*
 * Kimlik uçları.
 *
 * /oauth/authorize ve /oauth/token Passport'tan gelir. Buradakiler ikisini
 * tamamlar: istemci kurulumu için keşif belgesi, giriş sonrası kimliği okumak
 * için kullanıcı ucu.
 */
Route::get('.well-known/openid-configuration', DiscoveryController::class)->name('identity.discovery');
Route::get('oauth/userinfo', UserInfoController::class)->name('identity.userinfo');
