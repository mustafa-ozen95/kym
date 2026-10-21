<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\EmailVerification;
use App\Services\Permission\PermissionDictionary;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hesapları onaylar, askıya alır, rol verir.
 *
 * Kayıt açık olduğu için bu ekran zorunlu: onaylayan biri olmazsa açılan
 * hesaplar sonsuza dek bekler.
 *
 * KENDİ ROLÜNÜ DEĞİŞTİREMEZSİN. "Kullanıcıları yönet" yetkisi, kendini
 * yönetici yapma yetkisi değildir; son yöneticinin kendini düşürüp paneli
 * sahipsiz bırakması da engellenir.
 */
class UserController extends Controller implements HasMiddleware
{
    public function __construct(private PermissionDictionary $sozluk) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:kullanici.gor'], only: ['index']),
            new Middleware(['auth', 'can:kullanici.ekle'], only: ['store']),

            /*
             * Güncellemede yetki ALAN ALAN sorulur: bilgileri düzenlemek,
             * durumu değiştirmek ve rol atamak ayrı işler ve ayrı yetkiler.
             * Tek bir ara katman bunu söyleyemez.
             */
            new Middleware(['auth', 'can:kullanici.gor'], only: ['update']),

            /* Silme YALNIZCA yöneticide: ara katman listeyi görmeyi ister, asıl karar destroy() içinde. */
            new Middleware(['auth', 'can:kullanici.gor'], only: ['destroy']),
        ];
    }

    public function index(#[CurrentUser] $kullanici): View
    {
        $rolVerebilir = $kullanici->can('kullanici.rol');

        return view('admin.users', [
            /*
             * N+1: her satır rolünün adını, rozetini ve (yetki varsa) kişisel
             * istisnalarını basıyor.
             */
            'kullanicilar' => User::with(['rol', 'yetkiler'])->orderBy('durum')->orderBy('name')->get(),

            /* Seçenekler BURADA hazırlanır; Blade yalnızca basar. */
            'durumSecenekleri' => collect(Status::cases())->mapWithKeys(fn (Status $d): array => [$d->value => $d->label()])->all(),
            'rolSecenekleri' => Role::sirali()->pluck('label', 'uuid')->all(),

            /*
             * ROL ATAMAK AYRI BİR YETKİ. "Hesapları onayla" yetkisi olan biri
             * rol kutusunu göremez; görse bile controller geçirmez.
             */
            'rolVerebilir' => $rolVerebilir,
            'ekleyebilir' => $kullanici->can('kullanici.ekle'),
            'duzenleyebilir' => $kullanici->can('kullanici.duzenle'),
            'onaylayabilir' => $kullanici->can('kullanici.onayla'),

            /* Pencerenin ADRESİ vardır; doğrulama hatasında açık geri gelir. */
            'pencere' => request()->query('pencere') === 'ekle' ? 'ekle' : null,
            'duzenlenen' => request()->query('kayit')
                ? User::firstWhere('uuid', request()->query('kayit'))
                : null,
            'kaynaklar' => $rolVerebilir ? $this->sozluk->tumKaynaklar() : [],
        ]);
    }

    /**
     * Yönetici HESAP AÇAR.
     *
     * Kendi kendine kayıt kapatılabildiği için (bkz. RegistrationOpen) bu yol
     * gerekli; kapalı bir kurulumda hesabı yalnızca yönetici açar.
     *
     * Hesap ETKİN doğar: kaydı bir insan açıyor ve o kişi zaten adresin
     * sahibine kefil oluyor. Kendi kendine açılan hesabın beklemede doğması
     * ise bilinçli — orada kefil yok.
     */
    public function store(
        UserRequest $request,
        ActivityRecorder $recorder,
        EmailVerification $dogrulama,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        $veri = $request->validated();

        $yeni = User::create([
            'name' => $veri['name'],
            'email' => $veri['email'],
            'password' => $veri['password'],

            'rol_id' => $this->rolKimligi($veri, $kullanici) ?? Role::varsayilan()?->id,
        ]);

        /* Onayı veren kişi adresin o kişiye ait olduğuna kefil (bkz. update). */
        /* Durum toplu atamaya kapalı; yetkisi doğrulanmış yönetici açıkça yazar. */
        $yeni->durumuAyarla(Status::from($veri['durum'] ?? Status::Etkin->value));

        if ($yeni->durum === Status::Etkin && $dogrulama->onayDogrularMi()) {
            $yeni->forceFill(['email_verified_at' => now()])->save();
        }

        $recorder->record('kullanici-eklendi', $yeni->email.' hesabı açıldı', $kullanici);

        return redirect()->route('yonetim.kullanicilar')->with('status', $yeni->name.' hesabı açıldı.');
    }

    /**
     * Rol alanını YETKİYLE çözer.
     *
     * "Hesapları onayla" yetkisi rol atamayı kapsamaz; kapsasaydı onaylayan
     * kişi kendine yönetici rolü verebilirdi.
     *
     * @param  array<string, mixed>  $veri
     */
    private function rolKimligi(array $veri, User $kullanici): ?int
    {
        if (blank($veri['rol'] ?? null)) {
            return null;
        }

        abort_unless($kullanici->can('kullanici.rol'), 403);

        $rol = Role::where('uuid', $veri['rol'])->first();

        /*
         * YÖNETİCİ ROLÜNÜ YALNIZCA YÖNETİCİ VEREBİLİR.
         *
         * Aksi hâlde "rol ata" yetkisi olan biri kendine ya da bir tanıdığına
         * yönetici rolü verip yetkisini aşardı.
         */
        abort_if($rol?->is_admin && ! $kullanici->yonetici(), 403);

        return $rol?->id;
    }

    /**
     * Hesabı günceller — TEK PENCEREDEN.
     *
     * Ad, e-posta, parola, durum ve rol aynı formda; ayrı ayrı yerlerden
     * yapılırken satırda üç kutu duruyor ve ad değiştirmenin yolu hiç yoktu.
     *
     * YETKİ ALAN ALAN sorulur ve eksik yetkide istek REDDEDİLİR, alan sessizce
     * yok sayılmaz: yok sayılsaydı gövdeye alan ekleyen biri denemeye devam
     * eder ve bir gün kontrol unutulduğunda işe yarardı.
     */
    public function update(
        UserRequest $request,
        User $user,
        ActivityRecorder $recorder,
        EmailVerification $dogrulama,
        #[CurrentUser] $kullanici,
    ): RedirectResponse {
        /*
         * YÖNETİCİ HESABINA YALNIZCA YÖNETİCİ DOKUNUR.
         *
         * "Kullanıcıları düzenle" yetkisi (yönetim yalnızca yöneticide ayarı
         * kapatıldığında dağıtılabilir) bir yöneticinin e-postasını ve
         * parolasını değiştirme yetkisi DEĞİLDİR; değiştirebilseydi o hesabı
         * ele geçirmenin yolu olurdu. Karar alanlardan ÖNCE verilir: hangi
         * alan gelirse gelsin hedef yönetici ise istek reddedilir.
         */
        abort_if($user->yonetici() && ! $kullanici->yonetici(), 403);

        $veri = $request->validated();
        $degisiklik = [];

        if (array_key_exists('name', $veri) || array_key_exists('email', $veri) || filled($veri['password'] ?? null)) {
            abort_unless($kullanici->can('kullanici.duzenle'), 403);

            $degisiklik += array_filter([
                'name' => $veri['name'] ?? null,
                'email' => $veri['email'] ?? null,
                'password' => filled($veri['password'] ?? null) ? $veri['password'] : null,
            ], fn ($deger): bool => $deger !== null);
        }

        /*
         * KENDİ DURUMUNU VE ROLÜNÜ DEĞİŞTİREMEZSİN.
         *
         * "Kullanıcıları yönet" yetkisi kendini düşürme ya da paneli sahipsiz
         * bırakma yetkisi değildir. Adını düzeltmek ise serbest.
         */
        $kendisi = $user->is($kullanici);

        if (array_key_exists('durum', $veri) && ! $kendisi) {
            abort_unless($kullanici->can('kullanici.onayla'), 403);

            /* Toplu atamaya kapalı: yetki doğrulandıktan sonra açıkça yazılır. */
            $user->durumuAyarla(Status::from($veri['durum']));
        }

        if (filled($veri['rol'] ?? null) && ! $kendisi) {
            $degisiklik['rol_id'] = $this->rolKimligi($veri, $kullanici);
        }

        $user->update($degisiklik);

        /*
         * YÖNETİCİ ONAYI, E-POSTANIN DOĞRULANMASIDIR — doğrulama zorunlu
         * DEĞİLKEN.
         *
         * Varsayılan kurulumda kimseye doğrulama postası gitmiyor; hesabı bir
         * insan inceleyip etkinleştiriyor. Projeler ise ilk eşleşmede
         * "doğrulanmış e-posta" arıyor — bu işareti hiç koymasaydık hiçbir
         * hesap hiçbir projeye bağlanamazdı. Onayı veren kişi zaten adresin o
         * kişiye ait olduğuna kefil oluyor.
         */
        if ($user->durum === Status::Etkin && $user->email_verified_at === null && $dogrulama->onayDogrularMi()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $recorder->record(
            'kullanici-guncellendi',
            $user->email.' → '.$user->durum->label().', '.($user->fresh()->rol?->label ?? 'rolsüz')
                .(filled($veri['password'] ?? null) ? ' (parola değişti)' : ''),
            $kullanici,
        );

        /*
         * TEMİZ ADRESE dönülür, back()'e DEĞİL.
         *
         * Pencerenin adresi var (?pencere=duzenle&kayit=...) ve ekran o
         * adreste pencereyi kendiliğinden açıyor. back() deseydik kaydettikten
         * sonra aynı adrese dönerdik ve pencere hemen yeniden açılırdı:
         * kullanıcı "kaydet"e bastığında hiçbir şey olmamış gibi görünürdü.
         *
         * Doğrulama hatasında ise pencere AÇIK gelmeli; o yol buradan değil,
         * Form Request'in kendi geri dönüşünden geçiyor ve etkilenmiyor.
         */
        return redirect()->route('yonetim.kullanicilar')->with('status', $user->name.' güncellendi.');
    }

    /**
     * Hesabı siler — YALNIZCA YÖNETİCİ.
     *
     * Kendi hesabını silemezsin (paneli sahipsiz bırakmanın yolu olurdu).
     * Hesabın merkezdeki izleri de gider: oturumları ve projelere verilmiş
     * erişim jetonları (Passport tablolarında yabancı anahtar yok, silinmezse
     * jeton sahipsiz kalırdı). Denetim kaydı satırları OLDUĞU GİBİ kalır:
     * user_id özetin parçası (bkz. 2026_10_14_000100 göçü).
     * Projeler bildirimle (bkz. BroadcastsIdentityChange) haber alır.
     */
    public function destroy(User $user, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        abort_unless($kullanici->yonetici(), 403);
        abort_if($user->is($kullanici), 403);

        $ad = $user->name;
        $eposta = $user->email;

        DB::transaction(function () use ($user, $recorder, $kullanici, $eposta): void {
            $jetonlar = DB::table('oauth_access_tokens')->where('user_id', $user->getKey())->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $jetonlar)->delete();
            DB::table('oauth_access_tokens')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->getKey())->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $user->delete();

            $recorder->record('kullanici-silindi', $eposta.' hesabı silindi', $kullanici);
        });

        return redirect()->route('yonetim.kullanicilar')->with('status', $ad.' silindi.');
    }
}
