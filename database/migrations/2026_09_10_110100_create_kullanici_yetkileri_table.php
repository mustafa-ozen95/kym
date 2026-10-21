<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kişiye özel yetki istisnaları.
 *
 * Rol bir ŞABLONDUR; buradaki satırlar onun üstüne yazar. "Editör ama
 * hesapları da onaylasın" ya da "yönetici yardımcısı ama posta ayarına
 * dokunmasın" gibi durumlar yeni rol açmadan çözülür.
 *
 * `etki` iki değer alır: izin verir ya da AÇIKÇA REDDEDER. Reddin ayrı
 * durması gerekiyor çünkü şablonun verdiği bir yetkiyi geri almanın başka
 * yolu yok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kullanici_yetkileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            /* config/permissions.php içindeki "<kaynak>.<aksiyon>" anahtarı. */
            $table->string('yetenek', 60);

            $table->string('etki', 10)->default('izin');
            $table->timestamps();

            $table->unique(['user_id', 'yetenek']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kullanici_yetkileri');
    }
};
