<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDE.
 *
 * Merkezin yönetim bölümüne yönetici dışında kimse girmez: görüntüleme,
 * ekleme, düzenleme ve silme dahil.
 *
 * Asıl karar KODDA duruyor (PermissionResolver::YONETIM_YALNIZCA_YONETICI);
 * yalnızca veriyi temizlemek yetmezdi, bir kutucuk işaretlenince geri
 * açılırdı. Bu göç veriyi kodun söylediğiyle HİZALAR: ekranda "var" görünüp
 * hiçbir işe yaramayan yetki kalmasın.
 *
 * İKİ ŞEY TEMİZLENİR:
 *
 *   1. Yönetici OLMAYAN rollerin şablonları.
 *   2. Kişiye özel yetki istisnalarının TAMAMI — hem izin hem red. Red
 *      satırları da gider: artık kapatacak bir şey yok, duran satır ileride
 *      yetki yeniden açılırsa sessizce birinin yolunu keserdi.
 *
 * YETKİ DARALTILIYOR; göç kimseye yeni bir kapı açmıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roller')->where('is_admin', false)->update([
            'template' => json_encode([], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        DB::table('kullanici_yetkileri')->delete();
    }

    /**
     * Geri alırken YETKİ GERİ VERİLMEZ.
     *
     * Geri alma bir kurtarma işlemidir; kimseye yetki kazandırmamalı. Yetki
     * dağıtımı yeniden açılacaksa önce koddaki sabit değişir, sonra yetkiler
     * Roller ekranından bilinçli olarak verilir.
     */
    public function down(): void
    {
        // Kasıtlı olarak boş.
    }
};
