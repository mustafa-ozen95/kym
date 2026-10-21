<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ORTAK ÇIKIŞ: kullanıcının son çıkış anı, milisaniye.
 *
 * Merkezden ya da bağlı bir panelden çıkılınca yazılır; bu andan ÖNCE
 * açılmış her oturum (merkezde ve panellerde) bir sonraki istekte kapanır
 * (bkz. App\Services\SharedLogout). Milisaniye: saniye, çıkıştan hemen sonra
 * açılan oturumu eskisinden ayıramıyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('son_cikis_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('son_cikis_ms');
        });
    }
};
