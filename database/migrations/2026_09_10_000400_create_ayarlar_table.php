<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel ayarları.
 *
 * Ayarların .env yerine burada durmasının sebebi: bunlar sunucuya erişmeden,
 * ekrandan değiştirilebilmeli. .env değiştirmek dosya erişimi ister ve
 * değişiklik kimin yaptığı belli olmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayarlar', function (Blueprint $table): void {
            $table->string('anahtar')->primary();
            $table->text('deger')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayarlar');
    }
};
