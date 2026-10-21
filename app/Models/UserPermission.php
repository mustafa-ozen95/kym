<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rolün üstüne yazan kişisel istisna.
 *
 * `etki` ya izin verir ya da AÇIKÇA REDDEDER; reddin ayrı durması gerekiyor
 * çünkü şablonun verdiği bir yetkiyi geri almanın başka yolu yok.
 */
class UserPermission extends Model
{
    protected $table = 'kullanici_yetkileri';

    public const IZIN = 'izin';

    public const RED = 'red';

    protected $fillable = ['user_id', 'yetenek', 'etki'];

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
