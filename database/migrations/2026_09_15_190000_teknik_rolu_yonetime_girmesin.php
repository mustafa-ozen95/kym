<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TEKNİK rolü merkezin yönetimine girmez.
 *
 * Kural: teknik rolü yönetim ekranlarının hiçbirini açmaz; salt okuma
 * yetkisi de /yonetim kapısını ve üst menüdeki "Yönetim" bağlantısını
 * açacağı için şablonu boştur.
 *
 * Boş şablon kapıyı da kapatır: `yonet` geçidi "en az bir yönetim yetkisi var
 * mı" diye sorar (bkz. PermissionResolver::yonetimeGirebilirMi).
 *
 * Şablon config'ten okunur (bkz. config/permissions.php): burada ikinci bir
 * liste tutulsaydı ikisi bir gün sessizce ayrışırdı.
 *
 * YETKİ DARALTILIYOR; göç kimseye yeni bir kapı açmıyor. KİŞİSEL
 * İSTİSNALARA DOKUNULMAZ: birine tek tek bir yetki verilmişse o satır kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sablonlar = (array) config('permissions.templates', []);

        DB::table('roller')->where('slug', 'teknik')->update([
            'template' => json_encode($sablonlar['teknik'] ?? [], JSON_UNESCAPED_UNICODE),
            'description' => 'Merkezin yönetimine girmez; yalnızca kendisine açılan kutucukları kullanır.',
            'updated_at' => now(),
        ]);
    }

    /**
     * Geri alırken YETKİ GERİ VERİLMEZ.
     *
     * Geri alma bir kurtarma işlemidir; kimseye yetki kazandırmamalı. Rolün
     * eski hâli gerekiyorsa Roller ekranından bilinçli olarak verilir.
     */
    public function down(): void
    {
        // Kasıtlı olarak boş.
    }
};
