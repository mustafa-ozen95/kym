<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denetim kaydı.
 *
 * Merkez bütün sistemlerin kapısı; "kim, ne zaman, nereden girdi" sorusunun
 * yanıtı burada durur. Satırlar ZİNCİRLENİR: her satırın özeti bir öncekinin
 * özetini de kapsar, böylece aradan bir satır silmek ya da değiştirmek
 * zincirin geri kalanını bozar ve fark edilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('eylem', 40);
            $table->string('ozet');
            $table->string('ip', 45)->nullable();
            $table->string('onceki_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index('eylem');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
