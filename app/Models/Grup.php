<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Yetki\YetkiCozumleyici;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Hızlı Erişim sayfasındaki bir salon.
 *
 * Salon listesi iki yerde birden sabitti (çizim betiği ve ayar dosyası); yeni
 * bir salon açmak iki dosyayı düzenlemek demekti ve ikisi ayrışırsa ekleme
 * ekranında görünüp sayfada çizilmeyen bir grup ortaya çıkıyordu.
 */
class Grup extends Model
{
    use HasUuids;

    protected $table = 'gruplar';

    /** Kutucuklarla AYNI üç düzey; kural da aynı yerden gelir. */
    public const KAYNAK = 'grup-erisim';

    protected $fillable = ['anahtar', 'ad', 'not', 'sira', 'ayri_sayfa', 'gorunurluk'];

    protected $casts = ['sira' => 'integer', 'ayri_sayfa' => 'boolean'];

    protected $attributes = ['gorunurluk' => Sistem::HERKES];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Anahtar boşsa ADDAN türetilir — her yolda (bkz. Sistem::booted). */
    protected static function booted(): void
    {
        static::saving(function (self $grup): void {
            if (blank($grup->anahtar)) {
                $grup->anahtar = $grup->benzersizAnahtar(Str::slug((string) $grup->ad));
            }
        });
    }

    public function benzersizAnahtar(string $taban): string
    {
        $taban = $taban !== '' ? $taban : 'salon';
        $anahtar = $taban;
        $sayac = 2;

        while (static::where('anahtar', $anahtar)->whereKeyNot($this->getKey() ?? 0)->exists()) {
            $anahtar = $taban.'-'.$sayac++;
        }

        return $anahtar;
    }

    /** Bu salonu açmak için gereken yetki. */
    public function yetenek(): string
    {
        return self::KAYNAK.'.'.$this->anahtar;
    }

    /**
     * Kullanıcı bu salonu görebilir mi?
     *
     * Kutucuktakiyle AYNI kural: kopyalanmadı, aynı düzeyler ve aynı
     * çözümleyici. Ayrışsalardı salonu göremeyen içindeki kutucuğu görebilir
     * ya da tersi olurdu.
     */
    public function gorebilirMi(?User $kullanici): bool
    {
        return match ($this->gorunurluk) {
            Sistem::GIRISLI => $kullanici !== null,
            Sistem::YETKILI => $kullanici !== null
                && app(YetkiCozumleyici::class)->yapabilirMi($kullanici, $this->yetenek()),
            default => true,
        };
    }

    /** Ana sayfada bölüm olarak çizilecek salonlar: ayrı sayfası olanlar hariç. */
    public function scopeSayfada(Builder $query): Builder
    {
        return $query->where('ayri_sayfa', false);
    }

    public function sistemler(): HasMany
    {
        return $this->hasMany(Sistem::class, 'grup', 'anahtar');
    }

    public function scopeSirali(Builder $query): Builder
    {
        return $query->orderBy('sira')->orderBy('id');
    }

    /** Ekleme ekranının seçenek listesi: anahtar => ad. */
    public static function secenekler(): array
    {
        return static::sirali()->pluck('ad', 'anahtar')->all();
    }
}
