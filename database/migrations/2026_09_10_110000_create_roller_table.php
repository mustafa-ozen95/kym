<?php

declare(strict_types=1);

use App\Enums\BadgeVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Roller veriye taşınır.
 *
 * Rol bir KAYITTIR, hazır bir yetki şablonu taşır ve panelden düzenlenir:
 * iki değerli bir enum (yönetici / kullanıcı) ara bir yetki tanımlamaya izin
 * vermezdi.
 *
 * Eski enum değeri KORUNARAK taşınır: 'yonetici' → admin, 'kullanici' → uye.
 * Rolü çözülemeyen kimse yönetici YAPILMAZ; eksik veri yetki üretmemeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roller', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /* Kod, seeder ve şablon dosyası bu adla atıfta bulunur. */
            $table->string('slug', 40)->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('badge', 20)->default(BadgeVariant::Gray->value);

            /* Bütün kontrolleri geçen rol. En az biri KALMAK ZORUNDA. */
            $table->boolean('is_admin')->default(false);

            /* Yerleşik rol: silinemez, slug'ı değişmez. */
            $table->boolean('is_system')->default(false);

            /* Yeni kaydolan kullanıcının rolü. Yönetici rolü varsayılan olamaz. */
            $table->boolean('is_default')->default(false);

            /* Hazır yetki şablonu: ["sistem.gor", "kullanici.onayla", ...] */
            $table->json('template')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $this->yerlesikRoller();

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('rol_id')->nullable()->after('email')->constrained('roller');
        });

        $this->kullanicilariBagla();

        Schema::table('users', function (Blueprint $table): void {
            /*
             * İNDEKS ÖNCE DÜŞER.
             *
             * ['rol', 'durum'] birleşik indeksi eski sütuna dayanıyor; sqlite
             * sütunu ondan önce düşürmeye kalkınca "no such column" diyor ve
             * göç yarıda kalıyor.
             */
            $table->dropIndex(['rol', 'durum']);
            $table->dropColumn('rol');
            $table->index(['durum']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['durum']);
            $table->string('rol', 40)->default('kullanici')->after('email');
            $table->index(['rol', 'durum']);
        });

        foreach (DB::table('roller')->get() as $rol) {
            DB::table('users')->where('rol_id', $rol->id)
                ->update(['rol' => $rol->is_admin ? 'yonetici' : 'kullanici']);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['rol_id']);
            $table->dropColumn('rol_id');
        });

        Schema::dropIfExists('roller');
    }

    private function yerlesikRoller(): void
    {
        $sablonlar = (array) config('permissions.templates', []);

        $roller = [
            [
                'slug' => 'admin', 'label' => 'Yönetici', 'badge' => BadgeVariant::Purple->value,
                'is_admin' => true, 'position' => 0,
                'description' => 'Bütün yetki kontrollerini geçer. En az bir yönetici rolü kalmak zorundadır.',
            ],
            [
                'slug' => 'kullanici-yoneticisi', 'label' => 'Kullanıcı Yöneticisi',
                'badge' => BadgeVariant::Blue->value, 'position' => 1,
                'description' => 'Hesapları onaylar ve askıya alır; rol atayamaz, ayarlara dokunamaz.',
            ],
            [
                'slug' => 'editor', 'label' => 'İçerik Editörü',
                'badge' => BadgeVariant::Cyan->value, 'position' => 2,
                'description' => 'Hızlı Erişim kutucuklarını düzenler; hesaplara ve ayarlara dokunamaz.',
            ],
            [
                'slug' => 'uye', 'label' => 'Üye', 'badge' => BadgeVariant::Gray->value,
                'position' => 3, 'is_default' => true,
                'description' => 'Merkezin yönetimine girmez; yalnızca kendi hesabını yönetir.',
            ],
        ];

        // Örnek veri kapalıyken YALNIZCA yönetici rolü kurulur. Diğerleri
        // örnektir; kurulumu yapan kendi rollerini panelden açar.
        if (! config('app.ornek_veri')) {
            $roller = array_slice($roller, 0, 1);
        }

        foreach ($roller as $rol) {
            DB::table('roller')->insert([
                'is_admin' => false,
                'is_default' => false,
                ...$rol,
                'uuid' => (string) Str::uuid7(),
                'is_system' => true,
                'template' => json_encode($sablonlar[$rol['slug']] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function kullanicilariBagla(): void
    {
        $kimlikler = DB::table('roller')->pluck('id', 'slug');

        DB::table('users')->where('rol', 'yonetici')->update(['rol_id' => $kimlikler['admin']]);
        // 'uye' rolü yalnızca örnek veri açıkken vardır; yoksa rolsüz
        // kalırlar. Merkezde yeni hesap zaten BEKLEMEDE doğuyor ve hiçbir
        // yere erişemiyor; rolü yöneticinin ataması bekleniyor.
        if (isset($kimlikler['uye'])) {
            DB::table('users')->whereNull('rol_id')->update(['rol_id' => $kimlikler['uye']]);
        }
    }
};
