<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hızlı Erişim kutucukları.
 *
 * Liste sayfanın betiğinde sabit bir dizi değil, kayıttır: bir sistem eklemek
 * ya da çıkarmak kod düzenlemek demek olmasın; ekleme ve çıkarma ekrandan
 * yapılır.
 *
 * ŞERİT (serit) elle çizilmiş SVG animasyonlardan birini seçer (s1..s13).
 * Kabin kimliği (kabin) çizimdeki degrade tanımlarını benzersiz kılar; kaydın
 * kendisinden türetilir, kullanıcı girmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistemler', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('ad');
            $table->string('aciklama')->nullable();
            $table->string('adres')->nullable();
            $table->string('kod', 20)->nullable();

            /* Kutucuğun rengi; kabin ışığı ve şerit bu renkten beslenir. */
            $table->string('renk', 9)->default('#79c8a4');

            /* ic | mon | cam | dis — sayfadaki kabin salonları. */
            $table->string('grup', 20)->default('ic');
            $table->string('serit', 10)->nullable();
            $table->string('kabin', 10)->nullable();

            /* up | maint — bakımdaki sistem sönük çizilir ve tıklanmaz. */
            $table->string('durum', 20)->default('up');

            /* İsteğe bağlı ikinci bağlantı (örn. "Yedekler"). */
            $table->string('yan_etiket')->nullable();
            $table->string('yan_adres')->nullable();

            $table->unsignedInteger('sira')->default(0);
            $table->boolean('etkin')->default(true);
            $table->timestamps();

            $table->index(['grup', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sistemler');
    }
};
