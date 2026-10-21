<?php

declare(strict_types=1);

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HANGİ ROL HANGİ UYGULAMAYA GİREBİLİR — kararı MERKEZ verir.
 *
 * Her panel kendi listesini ("şu roller girebilir") tutsaydı aynı karar
 * birkaç yerde yazılı olur, biri değişince öbürleri sessizce eski kalır ve
 * bir rolün nereye eriştiğini görmek için her panele ayrı ayrı bakmak
 * gerekirdi.
 *
 * Erişim kapısı burada: istemciye bağlı rol listesi. LİSTE BOŞSA kapı
 * AÇIKTIR (bugünkü davranış korunur) — kısıt eklemek bilinçli bir adımdır,
 * kazara kimseyi dışarıda bırakmaz.
 *
 * Ayrıca "hesap kendiliğinden açılsın mı" da uygulama başına bir karardır:
 * merkezde rolü olan biri o panele ilk geldiğinde hesabı açılsın mı, yoksa
 * yönetici mi açsın?
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            /*
             * Merkezde rolü olan biri bu uygulamaya ilk geldiğinde hesabı
             * KENDİLİĞİNDEN açılsın mı? Kapalıyken uygulama "eşleşen hesap
             * yok" der ve hesabı oranın yöneticisi açar.
             */
            $table->boolean('otomatik_hesap')->default(false)->after('bildirim_sirri');
        });

        Schema::create('oauth_client_rol', function (Blueprint $table): void {
            $table->id();

            /*
             * Passport istemci kimliği uuid; yabancı anahtar ona göre.
             * Silinen istemcinin satırları da gider — kapı zaten yok.
             */
            $table->uuid('oauth_client_id');
            $table->foreignIdFor(Rol::class)->constrained('roller')->cascadeOnDelete();

            $table->unique(['oauth_client_id', 'rol_id']);

            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_rol');

        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('otomatik_hesap');
        });
    }
};
