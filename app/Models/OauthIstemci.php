<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\IstemciKokenleri;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

/**
 * Kimlik merkezine kayıtlı istemci (bir proje).
 *
 * ONAY EKRANI KARARI. Onay, üçüncü tarafa veri verirken anlamlıdır; buradaki
 * istemciler bizim kendi projelerimiz. Her girişte "izin veriyor musunuz"
 * sormak, kullanıcıyı okumadan tıklamaya alıştırmaktan başka işe yaramaz — ve
 * asıl önemli bir onay çıktığında o da tıklanıp geçilir. Ayrım kaydın
 * kendisinde: bir istemci bir kullanıcı adına açıldıysa (üçüncü taraf) onay
 * yine sorulur.
 *
 * ERİŞİM KAPISI DA BURADA. Hangi rolün hangi uygulamaya girebileceğine MERKEZ
 * karar verir; uygulamalar o kararı uygular. İçeride kimin neyi yapabileceği
 * ise uygulamanın kendi işidir — merkez kapıyı açar, yetkiyi proje verir.
 */
class OauthIstemci extends Client
{
    /**
     * Kayıt değişince güvenlik politikasındaki köken listesi tazelenir.
     *
     * Politika (CSP `form-action`) istemcilerin adreslerini taşıyor; liste
     * eskirse yeni eklenen panelin girişi sessizce çalışmaz olurdu
     * (bkz. App\Support\IstemciKokenleri).
     */
    protected static function booted(): void
    {
        static::saved(static fn () => IstemciKokenleri::unut());
        static::deleted(static fn () => IstemciKokenleri::unut());
    }

    /** @param  Scope[]  $scopes */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'otomatik_hesap' => 'boolean',
        ];
    }

    /**
     * Bu uygulamaya girebilen roller.
     *
     * BOŞ LİSTE = HERKES. Kısıt eklemek bilinçli bir adımdır; boş bırakılmış
     * bir liste "kimse giremesin" anlamına gelseydi, yeni açılan her istemci
     * kimsenin giremediği bir uygulama olurdu ve sebebi görünmezdi.
     *
     * @return BelongsToMany<Rol, $this>
     */
    public function roller(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'oauth_client_rol', 'oauth_client_id', 'rol_id');
    }

    /**
     * Bu rol bu uygulamanın nüfusunda mı? (Durumdan BAĞIMSIZ.)
     *
     * erisebilirMi "şu an girebilir mi"yi söyler; bu ise "bu uygulamanın
     * bilmesi gereken biri mi"yi. Askıya alınan kişi hâlâ kapsamdadır:
     * proje onu tanıyıp hesabını kapatabilmeli. Liste boşsa herkes
     * kapsamdadır (bkz. roller()).
     */
    public function rolKapsamdaMi(?int $rolId): bool
    {
        $izinli = $this->roller()->pluck('roller.id');

        return $izinli->isEmpty() || ($rolId !== null && $izinli->contains($rolId));
    }

    /**
     * İstemci kimliği ve sırrıyla gelen sunucu-sunucu çağrısının sahibi;
     * iptal edilmiş ya da sırrı tutmayan istemci null.
     */
    public static function dogrula(string $kimlik, string $sir): ?self
    {
        $istemci = self::find($kimlik);

        if ($istemci === null || $istemci->revoked) {
            return null;
        }

        return Hash::check($sir, (string) $istemci->secret) ? $istemci : null;
    }

    public function kapsamdaMi(?User $kullanici): bool
    {
        return $kullanici !== null && $this->rolKapsamdaMi($kullanici->rol_id);
    }

    /**
     * Bu kullanıcı bu uygulamaya girebilir mi?
     *
     * İki koşul: merkezdeki durumu girişe açık olmalı VE rolü bu uygulamanın
     * listesinde bulunmalı (liste boşsa rol aranmaz).
     *
     * ROLSÜZ KULLANICI, liste doluysa GİREMEZ: merkezde yeni açılmış, henüz
     * kimsenin bakmadığı hesap rolsüz duruyor ve rol atamak o kişiye bakılmış
     * olmasının kendisidir.
     */
    public function erisebilirMi(?User $kullanici): bool
    {
        if ($kullanici === null || ! $kullanici->durum->girebilir()) {
            return false;
        }

        $izinli = $this->roller()->pluck('roller.id');

        if ($izinli->isEmpty()) {
            return true;
        }

        return $kullanici->rol_id !== null && $izinli->contains($kullanici->rol_id);
    }

    /**
     * Bu uygulamada hesap KENDİLİĞİNDEN açılabilir mi?
     *
     * Uygulama bunu sorar ve kendi tarafında hesabı açar; merkez yalnızca
     * izni söyler. Erişemeyen birinin hesabı hiçbir koşulda açılmamalı, bu
     * yüzden erişim de burada sorulur.
     */
    public function hesapAcilabilirMi(?User $kullanici): bool
    {
        return $this->otomatik_hesap && $this->erisebilirMi($kullanici);
    }
}
