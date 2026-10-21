<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kutucuğun durumu TEK alan: etkin mi, değil mi.
 *
 * İki alan vardı ve ikisi aynı soruyu farklı sözcüklerle soruyordu:
 * `etkin` ("sayfada görünsün") ve `durum` (çevrimiçi / planlı bakım). Ekleme
 * ekranında ikisi de ayrı ayrı soruluyor, listede ise tek bir rozet olarak
 * birleştiriliyordu.
 *
 * `durum` üstelik ÖLÜ VERİYDİ: çizim betiği ona hiç bakmıyor, "bakımdaki"
 * kutucuk sayfada normal görünüyordu. Ekranda duran ama hiçbir şeyi
 * değiştirmeyen bir seçenek, olmayan bir denetimi varmış gibi gösterir.
 *
 * Bakımdaki sistemi ayrı göstermek bir gün gerekirse, o zaman ÇALIŞAN bir
 * özellik olarak eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* "Bakımda" işaretli kutucuklar sayfadan düşmesin: etkin kalırlar. */
        Schema::table('sistemler', function (Blueprint $table): void {
            $table->dropColumn('durum');
        });
    }

    public function down(): void
    {
        Schema::table('sistemler', function (Blueprint $table): void {
            $table->string('durum', 20)->default('up')->after('kabin');
        });

        DB::table('sistemler')->update(['durum' => 'up']);
    }
};
