<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EpostaDogrulamaController;
use App\Http\Controllers\Auth\GirisController;
use App\Http\Controllers\Auth\KayitController;
use App\Http\Controllers\Hesap\IkiAdimController;
use App\Http\Controllers\Hesap\ProfilController;
use App\Http\Controllers\HizliErisimController;
use App\Http\Controllers\KapiController;
use App\Http\Controllers\Oauth\KesifController;
use App\Http\Controllers\Oauth\KullaniciBilgisiController;
use App\Http\Controllers\Yonetim\AyarController;
use App\Http\Controllers\Yonetim\DenetimController;
use App\Http\Controllers\Yonetim\GrupController;
use App\Http\Controllers\Yonetim\GuncellemeController;
use App\Http\Controllers\Yonetim\IstemciController;
use App\Http\Controllers\Yonetim\KullaniciController;
use App\Http\Controllers\Yonetim\KullaniciYetkiController;
use App\Http\Controllers\Yonetim\RolController;
use App\Http\Controllers\Yonetim\SertifikaController;
use App\Http\Controllers\Yonetim\SistemController;
use App\Http\Controllers\Yonetim\VeritabaniController;
use App\Support\IstekSiniri;
use Illuminate\Support\Facades\Route;

/*
 * Hızlı Erişim: sistemlerin kapısı.
 *
 * Giriş istemez — bilinçli karar (bkz. HizliErisimController). Kimin neyi
 * göreceği kutucuk ve salon düzeyinde ayarlanır; sayfanın tamamını oturum
 * arkasına alan bir ayar yoktur, gerekirse buraya `auth` eklenir.
 */
Route::get('/', HizliErisimController::class)->name('hizli-erisim');

/*
 * Salonun kendi sayfası.
 *
 * Ayrı sayfa olarak işaretlenen salonlar ana sayfaya karışmaz; buraya bir
 * kutucukla gidilir. Şifre sormaz — görünürlüğü kutucuklarınkiyle aynı üç
 * düzeyde ve yetkisi olmayan 404 alır (403 "böyle bir sayfa var" derdi).
 */
Route::get('salon/{grup:anahtar}', [HizliErisimController::class, 'salon'])->name('salon');

/*
 * Giriş ve kayıt PENCEREDEN yapılır ama uçları normal POST'tur: betik
 * yüklenmese de çalışır. Sınırlar ayardan gelir (bkz. config/limits.php).
 *
 * Sınır SIFIRSA ara katman hiç eklenmez; karar IstekSiniri'nde durur.
 * Doğrudan `'throttle:'.config(...)` yazmak sıfırı "dakikada sıfır istek"e
 * çeviriyordu, yani sınırı kaldırmak isteyen kişi ucu tümüyle kapatırdı.
 */
Route::middleware('guest')->group(function (): void {
    Route::post('giris', [GirisController::class, 'store'])
        ->middleware(IstekSiniri::rotaIcin('giris'))
        ->name('giris');

    /* İkinci adım: oturum HENÜZ açılmadan, oturumda bekleyen kimlikle. */
    Route::post('giris/dogrulama', [GirisController::class, 'dogrula'])
        ->middleware(IstekSiniri::rotaIcin('giris'))
        ->name('giris.dogrulama');

    Route::post('kayit', [KayitController::class, 'store'])
        ->middleware(IstekSiniri::rotaIcin('kayit'))
        ->name('kayit');

    /*
     * Yeni bağlantı isteği. Oturumsuz — doğrulanmamış kullanıcı giriş
     * yapamıyor; oturum şartı konsaydı kilitli kalırdı.
     */
    Route::post('eposta/dogrulama', [EpostaDogrulamaController::class, 'yenidenGonder'])
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
Route::get('eposta/dogrula/{id}/{hash}', [EpostaDogrulamaController::class, 'dogrula'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
 * Hesap ayarları: herkes yalnızca KENDİ hesabını yönetir.
 */
Route::middleware('auth')->prefix('hesap')->name('hesap.')->group(function (): void {
    Route::get('profil', [ProfilController::class, 'index'])->name('profil');
    Route::put('profil', [ProfilController::class, 'update'])->name('profil.kaydet');
    Route::put('parola', [ProfilController::class, 'parola'])->name('parola');

    Route::get('iki-adim', [IkiAdimController::class, 'index'])->name('iki-adim');
    Route::post('iki-adim', [IkiAdimController::class, 'store'])->name('iki-adim.baslat');
    Route::post('iki-adim/onay', [IkiAdimController::class, 'onayla'])->name('iki-adim.onay');
    Route::delete('iki-adim', [IkiAdimController::class, 'destroy'])->name('iki-adim.kapat');
});

Route::post('cikis', [GirisController::class, 'destroy'])
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
    Route::get('sistemler', [SistemController::class, 'index'])->name('sistemler');
    Route::post('sistemler', [SistemController::class, 'store'])->name('sistemler.ekle');
    /* Sıra ucu {sistem} rotasından ÖNCE: yoksa "sira" bir uuid sanılırdı. */
    Route::put('sistemler/sira/{grup:anahtar}', [SistemController::class, 'sirala'])->name('sistemler.sirala');
    Route::put('sistemler/{sistem}', [SistemController::class, 'update'])->name('sistemler.guncelle');
    Route::delete('sistemler/{sistem}', [SistemController::class, 'destroy'])->name('sistemler.sil');

    /* Salonlar: kutucukların dizildiği bölümler; bazıları kendi sayfası. */
    Route::get('salonlar', [GrupController::class, 'index'])->name('gruplar');
    Route::post('salonlar', [GrupController::class, 'store'])->name('gruplar.ekle');
    Route::put('salonlar/sira', [GrupController::class, 'sirala'])->name('gruplar.sirala');
    Route::put('salonlar/{grup}', [GrupController::class, 'update'])->name('gruplar.guncelle');
    Route::delete('salonlar/{grup}', [GrupController::class, 'destroy'])->name('gruplar.sil');

    Route::get('ayarlar', [AyarController::class, 'index'])->name('ayarlar');
    Route::put('ayarlar', [AyarController::class, 'update'])->name('ayarlar.kaydet');
    Route::put('ayarlar/posta', [AyarController::class, 'posta'])->name('ayarlar.posta');

    /* Deneme iletisi dışarıya çıkar; sınırlı, çünkü her basış gerçek posta gönderir. */
    Route::post('ayarlar/posta/deneme', [AyarController::class, 'postaSina'])
        ->middleware('throttle:5,1')
        ->name('ayarlar.posta.deneme');

    Route::get('kullanicilar', [KullaniciController::class, 'index'])->name('kullanicilar');
    Route::post('kullanicilar', [KullaniciController::class, 'store'])->name('kullanicilar.ekle');
    Route::put('kullanicilar/{user}', [KullaniciController::class, 'update'])->name('kullanicilar.guncelle');
    Route::delete('kullanicilar/{user}', [KullaniciController::class, 'destroy'])->name('kullanicilar.sil');

    /* Kişiye özel istisnalar: rolün şablonunun üstüne yazar. */
    Route::get('kullanicilar/{user}/yetkiler', [KullaniciYetkiController::class, 'show'])
        ->name('kullanicilar.yetkiler');
    Route::put('kullanicilar/{user}/yetkiler', [KullaniciYetkiController::class, 'update'])
        ->name('kullanicilar.yetkiler.kaydet');

    /*
     * Bağlı uygulamalar: yeni bir siteyi merkeze bağlamanın TEK yeri.
     *
     * "Sil" yok, "kaldır" var: kayıt silinirse denetim kaydındaki "şu
     * uygulama şu kullanıcıyı sordu" satırları sahipsiz kalır.
     */
    Route::get('uygulamalar', [IstemciController::class, 'index'])->name('istemciler');
    Route::post('uygulamalar', [IstemciController::class, 'store'])->name('istemciler.ekle');

    /* Projeye konacak tek dosya; sır İÇİNDE DEĞİL, .env'den okunur. */
    Route::get('uygulamalar/{istemci}/kurulum', [IstemciController::class, 'kurulumDosyasi'])
        ->name('istemciler.kurulum');
    Route::put('uygulamalar/{istemci}', [IstemciController::class, 'update'])->name('istemciler.guncelle');
    Route::put('uygulamalar/{istemci}/sir', [IstemciController::class, 'sirYenile'])->name('istemciler.sir');
    Route::delete('uygulamalar/{istemci}', [IstemciController::class, 'destroy'])->name('istemciler.kaldir');
    Route::delete('uygulamalar/{istemci}/kalici', [IstemciController::class, 'kaliciSil'])->name('istemciler.kalici-sil');

    /*
     * Roller, Ayarlar'ın bir SEKMESİDİR (bkz. AyarBolumleri): kullanıcı onu
     * Ayarlar'ın altında arar.
     */
    /*
     * Denetim kaydı da Ayarlar'ın bir sekmesi. SİLME UCU YOK ve olmayacak:
     * silinebilen bir denetim kaydı, denetim kaydı değildir.
     */
    /*
     * SERTİFİKA. Tur sunucuda koşar; panel isteği bırakır ve sonucu okur
     * (bkz. SertifikaController).
     */
    Route::get('ayarlar/sertifika', [SertifikaController::class, 'index'])->name('sertifika');
    Route::post('ayarlar/sertifika/tur', [SertifikaController::class, 'baslat'])->name('sertifika.baslat');
    Route::post('ayarlar/sertifika/onayla', [SertifikaController::class, 'tamamla'])->name('sertifika.tamamla');

    /*
     * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır; kopya
     * yeniden başlatmada, merkez kapalıyken yapılır (bkz. VeritabaniController
     * ve BaglantiDosyasi).
     */
    Route::get('ayarlar/veritabani', [VeritabaniController::class, 'index'])->name('veritabani');
    Route::post('ayarlar/veritabani/sina', [VeritabaniController::class, 'sina'])->name('veritabani.sina');
    Route::post('ayarlar/veritabani/tasi', [VeritabaniController::class, 'tasi'])->name('veritabani.tasi');

    /*
     * GÜNCELLEME. Yalnızca bakar: çalışan sürüm GitHub'daki son başarılı
     * derlemeyle karşılaştırılır (bkz. GuncellemeDenetimi).
     */
    Route::get('ayarlar/guncelleme', [GuncellemeController::class, 'index'])->name('guncelleme');
    Route::post('ayarlar/guncelleme', [GuncellemeController::class, 'denetle'])->name('guncelleme.denetle');

    Route::get('ayarlar/kayitlar', [DenetimController::class, 'index'])->name('kayitlar');
    Route::post('ayarlar/kayitlar/dogrula', [DenetimController::class, 'dogrula'])->name('kayitlar.dogrula');

    Route::get('ayarlar/roller', [RolController::class, 'index'])->name('roller');
    Route::post('ayarlar/roller', [RolController::class, 'store'])->name('roller.ekle');
    Route::get('ayarlar/roller/{rol}', [RolController::class, 'show'])->name('roller.goster');
    Route::put('ayarlar/roller/{rol}', [RolController::class, 'update'])->name('roller.guncelle');
    Route::delete('ayarlar/roller/{rol}', [RolController::class, 'destroy'])->name('roller.sil');
});

/*
 * KAPI — korunan sayfanın bekçisi.
 *
 * `auth` ara katmanının DIŞINDA ve bilinçli: o katman kimliksiz isteği giriş
 * sayfasına yönlendirir, nginx `auth_request` ise 2xx/401/403 dışındaki her
 * yanıtı 500 sayar. Kimlik kontrolü controller içinde yapılır ve 401 döner.
 */
Route::middleware('web')->get('kapi/{anahtar}', KapiController::class)->name('kapi');

/*
 * Kimlik uçları.
 *
 * /oauth/authorize ve /oauth/token Passport'tan gelir. Buradakiler ikisini
 * tamamlar: istemci kurulumu için keşif belgesi, giriş sonrası kimliği okumak
 * için kullanıcı ucu.
 */
Route::get('.well-known/openid-configuration', KesifController::class)->name('kimlik.kesif');
Route::get('oauth/kullanici', KullaniciBilgisiController::class)->name('kimlik.kullanici');
