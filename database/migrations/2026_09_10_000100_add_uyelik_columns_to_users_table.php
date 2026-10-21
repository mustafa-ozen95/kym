<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkezin kendi üyelik alanları.
 *
 * DURUM alanı kaydın açık olmasını güvenli kılan şeydir: hesap açılır ama
 * "beklemede" doğar, yönetici etkinleştirene kadar giriş yapamaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->nullable()->unique()->after('id');
            $table->string('rol', 20)->default('kullanici')->after('password');
            $table->string('durum', 20)->default('beklemede')->after('rol');
            $table->timestamp('son_giris_at')->nullable()->after('durum');

            $table->index(['rol', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['rol', 'durum']);
            $table->dropColumn(['uuid', 'rol', 'durum', 'son_giris_at']);
        });
    }
};
