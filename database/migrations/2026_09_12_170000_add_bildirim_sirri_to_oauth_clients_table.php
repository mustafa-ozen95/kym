<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * İstemciye BİLDİRİM SIRRI.
 *
 * Merkez, rol ya da durum değişince bağlı projelere haber veriyor ve bu
 * bildirimi imzalıyor. İmza için iki tarafın paylaştığı bir sır gerekiyor;
 * OAuth istemci sırrı bu iş için KULLANILAMAZ çünkü merkezde bcrypt özeti
 * olarak duruyor — merkez onu doğrulayabilir ama üretemez.
 *
 * Bu yüzden ayrı ve DÜZ METİN bir sır: yalnızca bildirimi imzalar, hiçbir
 * yetki taşımaz. Ele geçse bile yapılabilecek tek şey, projenin merkezle
 * hizalanmasını erkene almaktır (proje gelen veriye güvenmiyor, doğruyu
 * merkezden kendi soruyor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->string('bildirim_sirri', 64)->nullable()->after('secret');
        });

        /*
         * VAR OLAN İSTEMCİLERE DE ÜRETİLİR.
         *
         * Boş bırakılsaydı bugüne kadar bağlanmış projeler bildirim
         * alamayacak ve fark yalnızca "neden geç uyguluyor" sorusuyla
         * anlaşılacaktı. Sır üretilir; projeye yazmak yöneticinin işidir ve
         * yazılana kadar o proje beş dakikalık turla çalışmaya devam eder.
         */
        DB::table('oauth_clients')->whereNull('bildirim_sirri')->get(['id'])
            ->each(fn (object $satir) => DB::table('oauth_clients')
                ->where('id', $satir->id)
                ->update(['bildirim_sirri' => Str::random(48)]));
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('bildirim_sirri');
        });
    }
};
