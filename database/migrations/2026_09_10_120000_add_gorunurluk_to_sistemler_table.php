<?php

declare(strict_types=1);

use App\Models\Sistem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kutucuk görünürlüğü.
 *
 * Hızlı Erişim herkese açık bir sayfa ve şimdiye kadar bütün kutucuklar
 * herkese görünüyordu. Oysa bazı sistemlerin VARLIĞI bile herkesi
 * ilgilendirmiyor: adı, adresi ve hangi kabinde durduğu tek başına bilgidir.
 *
 * Üç düzey:
 *   herkes   — oturum gerekmez (varsayılan; bugünkü davranış)
 *   girisli  — yalnızca giriş yapmış hesaplar
 *   yetkili  — yalnızca o kutucuğun yetkisi olanlar
 *
 * ANAHTAR (slug) yetki adının okunur yarısıdır: yetki "sistem-erisim.<anahtar>"
 * olarak sorulur. uuid kullanılsaydı rol ekranında kimsenin tanıyamayacağı
 * kutucuklar dizilirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sistemler', function (Blueprint $table): void {
            $table->string('anahtar', 60)->nullable()->unique()->after('kod');
            $table->string('gorunurluk', 20)->default(Sistem::HERKES)->after('etkin');
            $table->index(['gorunurluk']);
        });

        $this->anahtarlariDoldur();

        Schema::table('sistemler', function (Blueprint $table): void {
            $table->string('anahtar', 60)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sistemler', function (Blueprint $table): void {
            $table->dropIndex(['gorunurluk']);
            $table->dropColumn(['anahtar', 'gorunurluk']);
        });
    }

    /**
     * Mevcut kutucuklara anahtar üretir.
     *
     * Ad Türkçe olabiliyor; `Str::slug` aksanları çeviriyor. Çakışma olursa
     * sona sayı eklenir — anahtar benzersiz olmak zorunda, yetki adının
     * yarısı o.
     */
    private function anahtarlariDoldur(): void
    {
        $kullanilan = [];

        foreach (DB::table('sistemler')->orderBy('id')->get() as $sistem) {
            $taban = Str::slug($sistem->ad) ?: 'sistem';
            $anahtar = $taban;
            $sayac = 2;

            while (in_array($anahtar, $kullanilan, true)) {
                $anahtar = $taban.'-'.$sayac++;
            }

            $kullanilan[] = $anahtar;

            DB::table('sistemler')->where('id', $sistem->id)->update(['anahtar' => $anahtar]);
        }
    }
};
