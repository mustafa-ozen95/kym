<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BroadcastsIdentityChange;
use App\Models\Concerns\ClosesSessions;
use App\Services\EmailVerification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

/**
 * Merkezin kullanıcısı.
 *
 * Sıralı birincil anahtar dışarı çıkmaz; ekranlar ve adresler uuid taşır.
 *
 * `MustVerifyEmail` her zaman uygulanır ama doğrulamanın ŞART olup olmadığını
 * o belirlemez: kararı `EmailVerification` verir. Sözleşme sabit, kural
 * ayarlanabilir — böylece zorunluluk kapalıyken de imzalı doğrulama
 * bağlantısı üretilebilir.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use BroadcastsIdentityChange;
    use ClosesSessions;
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /*
     * DURUM TOPLU ATAMAYA KAPALI.
     *
     * Bir hesabın etkinleşmesi yöneticinin kararıdır ve başka hiçbir yoldan
     * olamaz. Alan burada dursaydı,
     * kullanıcıdan veri alan HERHANGİ bir güncelleme ucu — bugün olmayan,
     * yarın eklenecek bir tanesi — gövdeye "durum=etkin" yazan birine
     * açılırdı. Doğrulama kurallarına güvenmek yetmez: unutulan tek bir kural
     * sessizce yetki verir.
     *
     * Meşru iki yazıcı durumu AÇIKÇA yazar (forceFill): kayıt ekranı
     * "beklemede", kullanıcı yönetimi ekranı ise yalnızca
     * `kullanici.onayla` yetkisini doğruladıktan sonra.
     */
    protected $fillable = ['name', 'email', 'password', 'rol_id'];

    /* Gizli anahtar ve kurtarma kodları hiçbir yanıtta görünmez. */
    protected $hidden = ['password', 'remember_token', 'totp_secret', 'totp_recovery'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'son_giris_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_recovery' => 'encrypted:array',
            'totp_confirmed_at' => 'datetime',
            'durum' => Status::class,
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * ADRES DEĞİŞİNCE "DOĞRULANMIŞ" İŞARETİ DÜŞER.
     *
     * Bu panel bir kimlik sağlayıcısıdır: `email_verified` iddiasını bağlı
     * projelere (EYS, NYS) gönderiyor ve o projeler kimliği YEREL hesapla ilk
     * kez eşleştirirken bu iddiaya bakıyor. Doğrulanmış sayılan bir adres,
     * yereldeki aynı adresli hesaba bağlanıyor.
     *
     * İşaret düşmeseydi: burada onaylanmış herhangi bir kullanıcı kendi
     * profilinden e-postasını bir meslektaşının adresiyle değiştirir, adresin
     * kendisine ait olduğunu hiç kanıtlamadığı hâlde iddia "true" gitmeye
     * devam eder ve bağlı projede o adrese karşılık gelen, henüz hiçbir
     * kimliğe bağlanmamış hesap onun olurdu. Kurulumdan sonra
     * `kimlik:yonetici-ekle` ile açılan hesap tam olarak böyledir — ilk SSO
     * girişine kadar bağsız durur.
     *
     * KURAL MODELDE, denetleyicide DEĞİL: adres üç ayrı yerden
     * değiştirilebiliyor (profil, yönetim ekranı, konsol komutu) ve kuralı
     * her birine ayrı ayrı yazmak, dördüncüsü eklendiğinde unutmanın yoluydu.
     *
     * İşareti AYNI KAYITTA bilerek koyan çağrı korunur: yönetici onayı
     * (bkz. Admin\UserController) ve ilk yönetici komutu adresi ve
     * damgayı birlikte yazar; orada karar veren bir insan vardır.
     */
    protected static function booted(): void
    {
        static::updating(function (self $kullanici): void {
            if ($kullanici->isDirty('email') && ! $kullanici->isDirty('email_verified_at')) {
                $kullanici->email_verified_at = null;
            }
        });

        /*
         * ONAY, ADRESİ DOĞRULANMIŞ SAYAR — hangi yoldan gelirse gelsin.
         *
         * Doğrulama zorunlu değilken adrese kefil olan kişi, hesabı
         * etkinleştiren yöneticidir (bkz. EmailVerification::onayDogrularMi).
         * Kural modelde durur ki hesabı başka bir yoldan (konsol, toplu işlem)
         * etkinleştiren de adımı atlamasın: atlanırsa SESSİZ bir arıza olur —
         * kişi merkeze girer ama kimliği yalnızca doğrulanmış adreste eşleyen
         * paneller "eşleşen hesap yok" der. Etkinleşen her hesap aynı şeyi
         * yaşar.
         */
        static::saving(function (self $kullanici): void {
            /* Yalnızca DURUM ETKİNE DÖNERKEN; her kaydetmede değil. */
            if (! $kullanici->isDirty('durum') || $kullanici->durum !== Status::Etkin) {
                return;
            }

            /*
             * Alan AÇIKÇA yazıldıysa dokunulmaz — null yazmak da bir karardır
             * ve onu ezmek, çağıranın dediğini görmezden gelmek olur.
             */
            if ($kullanici->isDirty('email_verified_at') || $kullanici->email_verified_at !== null) {
                return;
            }

            if (app(EmailVerification::class)->onayDogrularMi()) {
                $kullanici->email_verified_at = now();
            }
        });
    }

    /**
     * Hesabın durumunu yazar — TEK KAPI.
     *
     * Durum toplu atamaya kapalı (bkz. $fillable): bir hesabın etkinleşmesi
     * yöneticinin kararıdır. Yazmak meşru olduğunda bu yöntemle yazılır;
     * böylece "kim durum değiştiriyor" sorusu kodda aranabilir kalır ve
     * `update(['durum' => ...])` yazan biri sessizce hiçbir şey yapmaz
     * değil, yanlış kapıyı çaldığını görür.
     *
     * YETKİ BURADA SORULMAZ: bu bir yazıcı, bekçi değil. Kararı veren yer
     * (kullanıcı yönetimi ekranı) yetkiyi kendisi doğrular.
     */
    public function durumuAyarla(Status $durum): void
    {
        $this->forceFill(['durum' => $durum])->save();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** İkinci adım kurulmuş VE onaylanmış mı? */
    public function ikiAdimliMi(): bool
    {
        return $this->totp_confirmed_at !== null && filled($this->totp_secret);
    }

    /** Rolün şablonunun üstüne yazan kişisel istisnalar. */
    public function yetkiler(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'user_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * Bütün kontrolleri geçen rol.
     *
     * Tek tek yetkiler için bunu SORMA: `$user->can('sistem.duzenle')` de.
     * Bu yöntem yalnızca "yönetici mi" sorusunun kendisi içindir (son
     * yöneticinin kendini düşürmesini engellemek gibi).
     */
    /**
     * Yönetici rolündeki hesaplar.
     *
     * Rol bir sütun değil; "yönetici mi" sorusu rol tablosundan geçer ve
     * birden fazla yönetici rolü tanımlanabilir.
     *
     * ADI ÇOĞUL: `scopeYonetici` olsaydı `User::yonetici()` çağrısı aşağıdaki
     * ÖRNEK yöntemle çakışırdı ve PHP "non-static method" diye patlardı.
     */
    public function scopeYoneticiler(Builder $query): void
    {
        $query->whereHas('rol', fn (Builder $rol) => $rol->where('is_admin', true));
    }

    public function yonetici(): bool
    {
        return (bool) $this->rol?->is_admin;
    }

    /** Girişe izin var mı? Durum tek karar noktasıdır. */
    public function girebilir(): bool
    {
        return $this->durum->girebilir();
    }
}
