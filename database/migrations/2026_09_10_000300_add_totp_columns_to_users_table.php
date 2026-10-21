<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İkinci adım (TOTP).
 *
 * Gizli anahtar ŞİFRELİ saklanır: veritabanını okuyabilen biri onunla geçerli
 * kod üretebilirdi, yani ikinci adım hiç yokmuş gibi olurdu.
 *
 * Kurtarma kodları da özetlenerek saklanır — düz metin tutulsaydı sızıntı
 * hâlinde ikinci adımı atlamanın hazır anahtarı olurlardı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable()->after('son_giris_at');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
            $table->text('totp_recovery')->nullable()->after('totp_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at', 'totp_recovery']);
        });
    }
};
