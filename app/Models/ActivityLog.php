<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denetim kaydının bir satırı. Yazıldıktan sonra DEĞİŞTİRİLMEZ.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'actor_name', 'eylem', 'ozet', 'ip', 'onceki_hash', 'hash', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
