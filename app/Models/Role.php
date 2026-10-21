<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BadgeVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Merkezin rolü.
 *
 * Rol tek başına yetki DEĞİLDİR; hazır bir yetki şablonudur. Şablon
 * uygulandıktan sonra kişiye özel istisnalar yetki ekranından verilir
 * (bkz. UserPermission).
 *
 * Yönetici rolü (`is_admin`) bunun dışındadır: bütün kontrolleri geçer.
 * En az bir yönetici rolü KALMAK ZORUNDA; yoksa merkezi kimse yönetemez.
 *
 * Yerleşik roller (`is_system`) silinemez ve slug'ı değişmez: göç, şablon
 * dosyası ve testler onlara adıyla atıfta bulunuyor.
 */
class Role extends Model
{
    use HasUuids;

    protected $table = 'roller';

    protected $fillable = ['slug', 'label', 'description', 'badge', 'is_admin', 'is_default', 'template', 'position'];

    protected function casts(): array
    {
        return [
            'badge' => BadgeVariant::class,
            'is_admin' => 'boolean',
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'template' => 'array',
            'position' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function kullanicilar(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    /**
     * Rolün hazır yetkileri.
     *
     * @return list<string>
     */
    public function yetenekler(): array
    {
        return array_values(array_filter((array) $this->template, 'is_string'));
    }

    public function scopeSirali(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }

    /** Yeni kaydolanın rolü. Yoksa hiçbiri: hesap rolsüz kalır ve hiçbir şey yapamaz. */
    public static function varsayilan(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
