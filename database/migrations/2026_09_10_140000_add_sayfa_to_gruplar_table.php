<?php

declare(strict_types=1);

use App\Models\System;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salon KENDİ SAYFASI olabilir.
 *
 * Örnek: "Salon A". İçindekilerin ana sayfaya karışması istenmez — orası "hangi
 * sisteme gidiyorsun" sorusunun yanıtı; ayrı sayfalı salon ise ayrı bir iş.
 *
 * Ayrı sayfa olan salon ana sayfada BÖLÜM OLARAK ÇİZİLMEZ; kendi adresinde
 * durur ve oraya bir kutucukla gidilir.
 *
 * GÖRÜNÜRLÜK kutucuklardakiyle aynı üç düzey: şifre yerine yetki.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gruplar', function (Blueprint $table): void {
            $table->boolean('ayri_sayfa')->default(false)->after('not');
            $table->string('gorunurluk', 20)->default(System::HERKES)->after('ayri_sayfa');
        });
    }

    public function down(): void
    {
        Schema::table('gruplar', function (Blueprint $table): void {
            $table->dropColumn(['ayri_sayfa', 'gorunurluk']);
        });
    }
};
