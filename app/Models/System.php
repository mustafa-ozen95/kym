<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BadgeVariant;
use App\Services\Permission\PermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Hızlı Erişim sayfasındaki bir kutucuk.
 *
 * Sıralı birincil anahtar dışarı çıkmaz; ekranlar ve adresler uuid taşır.
 *
 * GÖRÜNÜRLÜK üç düzeyli. Bazı sistemlerin VARLIĞI bile herkesi
 * ilgilendirmiyor: adı, adresi ve hangi kabinde durduğu tek başına bilgidir.
 * Bu yüzden yetkisi olmayan kutucuğu görmez — sönük ya da kilitli değil,
 * HİÇ YOK.
 */
class System extends Model
{
    use HasUuids;

    protected $table = 'sistemler';

    /** Oturum gerekmez; bugünkü davranış ve varsayılan. */
    public const EVERYONE = 'herkes';

    /** Giriş yapmış her hesap görür. */
    public const LOGGED_IN = 'girisli';

    /** Yalnızca bu kutucuğun yetkisi olanlar görür. */
    public const AUTHORIZED = 'yetkili';

    /** @var list<string> */
    public const VISIBILITIES = [self::EVERYONE, self::LOGGED_IN, self::AUTHORIZED];

    /** Kutucuk yetkilerinin kaynak adı: "sistem-erisim.<anahtar>". */
    public const RESOURCE = 'sistem-erisim';

    protected $fillable = [
        'ad', 'aciklama', 'adres', 'kod', 'anahtar', 'renk',
        'grup', 'serit',
        'yan_etiket', 'yan_adres', 'sira', 'etkin', 'gorunurluk',
    ];

    protected $casts = [
        'etkin' => 'boolean',
        'sira' => 'integer',
    ];

    /** Yeni kutucuk bugünkü davranışla doğar: herkese açık. */
    protected $attributes = [
        'gorunurluk' => self::EVERYONE,
    ];

    /**
     * Anahtar boşsa ADDAN türetilir — HER YOLDA.
     *
     * Fabrika, tohumlayıcı, konsol ve form aynı kaydı ayrı yollardan yazıyor;
     * türetme yalnızca formda olsaydı diğer yollar boş anahtarla kayıt açar ve
     * o kutucuğun yetkisi hiç oluşmazdı.
     */
    protected static function booted(): void
    {
        static::saving(function (self $sistem): void {
            if (blank($sistem->anahtar)) {
                $sistem->anahtar = $sistem->benzersizAnahtar(Str::slug((string) $sistem->ad));
            }
        });
    }

    /** Çakışma olursa sona sayı eklenir; anahtar benzersiz olmak zorunda. */
    public function benzersizAnahtar(string $taban): string
    {
        $taban = $taban !== '' ? $taban : 'sistem';
        $anahtar = $taban;
        $sayac = 2;

        while (static::where('anahtar', $anahtar)->whereKeyNot($this->getKey() ?? 0)->exists()) {
            $anahtar = $taban.'-'.$sayac++;
        }

        return $anahtar;
    }

    /** uuid dışındaki anahtarlar otomatik üretilmez. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Kutucuğun ekrandaki durumu ve rozet rengi.
     *
     * TEK SORU: sayfada mı, değil mi. Önce iki alan vardı (`etkin` ve `durum`)
     * ve `durum` ölü veriydi — çizim betiği ona hiç bakmıyor, "bakımdaki"
     * kutucuk sayfada normal görünüyordu.
     *
     * Görünüm kararı modelde: Blade içinde match yazmak, aynı kuralı iki
     * ekrana kopyalamak demekti.
     *
     * İKİ AYRI ÖZELLİK, tek bir çift değil: tek bir dizi dönseydi ekran onu
     * açmak için Blade içinde PHP yazmak zorunda kalırdı — nitekim kalıyordu.
     *
     * @return Attribute<string, never>
     */
    protected function durumEtiketi(): Attribute
    {
        return Attribute::get(fn (): string => $this->etkin ? 'Aktif' : 'Pasif');
    }

    /** @return Attribute<BadgeVariant, never> */
    protected function durumRengi(): Attribute
    {
        return Attribute::get(fn (): BadgeVariant => $this->etkin
            ? BadgeVariant::Green
            : BadgeVariant::Gray);
    }

    /** Bu kutucuğu açmak için gereken yetki. */
    public function yetenek(): string
    {
        return self::RESOURCE.'.'.$this->anahtar;
    }

    /**
     * Kullanıcı bu kutucuğu görebilir mi?
     *
     * Kural TEK YERDE: hem sayfa listesi hem de kapı ucu buradan geçer.
     * Kopyalansaydı biri değişince diğeri sessizce eski kalır ve liste ile
     * kapı farklı yanıt verirdi.
     */
    public function gorebilirMi(?User $kullanici): bool
    {
        return match ($this->gorunurluk) {
            self::LOGGED_IN => $kullanici !== null,

            /*
             * Yetki Gate'e KAYDEDİLMEZ, doğrudan çözümleyiciye sorulur.
             *
             * Kutucuk yetkileri kayıtlardan türüyor; Gate'e kaydetmek için
             * uygulama her açılışta kutucuk tablosunu okumak zorunda kalırdı —
             * göç sırasında tablo henüz yokken de.
             */
            self::AUTHORIZED => $kullanici !== null
                && app(PermissionResolver::class)->yapabilirMi($kullanici, $this->yetenek()),

            default => true,
        };
    }

    public function scopeEtkin(Builder $query): Builder
    {
        return $query->where('etkin', true);
    }

    /**
     * Sayfada gösterilecek kutucuklar.
     *
     * SÜZME VERİTABANINDA: "hepsini getir sonra ekranda gizle" güvenlik
     * değildir — kaynak koda bakan kutucuğun adını yine görür.
     *
     * `yetkili` düzeyindekiler tek tek sorulur; yetenek adı kaydın kendisinden
     * türüyor ve sorguya çevrilemiyor. Sayı küçük (bir sayfa dolusu kutucuk),
     * sorgu yok — yalnızca zaten yüklenmiş kullanıcının yetki listesine bakılır.
     */
    public function scopeGorunur(Builder $query, ?User $kullanici): Builder
    {
        return $query->etkin()->where(function (Builder $q) use ($kullanici): void {
            $q->where('gorunurluk', self::EVERYONE);

            if ($kullanici !== null) {
                $q->orWhere('gorunurluk', self::LOGGED_IN)
                    ->orWhere('gorunurluk', self::AUTHORIZED);
            }
        });
    }

    /**
     * Sayfada gösterilecek kutucuklar — SON hâli.
     *
     * Sorgu `yetkili` olanları da getiriyor (yetenek adı kayıttan türüyor ve
     * sorguya çevrilemiyor); son eleme burada yapılıyor. Tek çağrı noktası
     * olsun diye ayrı bir yöntem: iki ekran ayrı ayrı elerse biri unutulur.
     *
     * @return Collection<int, self>
     */
    public static function icinGorunenler(?User $kullanici)
    {
        return static::gorunur($kullanici)->sirali()->get()
            ->filter(fn (self $sistem): bool => $sistem->gorebilirMi($kullanici))
            ->values();
    }

    public function scopeSirali(Builder $query): Builder
    {
        return $query->orderBy('sira')->orderBy('id');
    }
}
