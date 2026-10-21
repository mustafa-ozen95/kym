<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yerleşik roller SALT OKUR olur.
 *
 * Merkezi değiştirmek yalnızca yöneticide; yönetici olmayan yerleşik rollerin
 * şablonları görüntülemeyle sınırlıdır. Yetki DARALTILIYOR, yani göç kimseye
 * yeni bir kapı açmıyor.
 *
 * Şablon config'ten okunur (bkz. config/permissions.php): burada ikinci bir
 * liste tutulsaydı ikisi bir gün sessizce ayrışırdı.
 *
 * KİŞİSEL İSTİSNALARA DOKUNULMAZ. Birine tek tek "sistem.duzenle" verilmişse
 * o satır kalır; bu göç rolün varsayılanını değiştiriyor, kimsenin bilinçli
 * verilmiş yetkisini silmiyor.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLLER = ['network', 'teknik', 'muhasebe'];

    public function up(): void
    {
        $sablonlar = (array) config('permissions.templates', []);

        $aciklamalar = [
            'network' => 'Hızlı Erişim kutucuklarını ve salonlarını görür; değiştirmez.',
            'teknik' => 'Neyin nerede olduğunu görür, değiştirmez.',
            'muhasebe' => 'Neyin nerede olduğunu görür; yalnızca kendisine açılan kutucukları kullanır.',
        ];

        foreach (self::ROLLER as $slug) {
            DB::table('roller')->where('slug', $slug)->update([
                'template' => json_encode($sablonlar[$slug] ?? [], JSON_UNESCAPED_UNICODE),
                'description' => $aciklamalar[$slug],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Geri alırken YAZMA YETKİSİ GERİ VERİLMEZ.
     *
     * Geri alma bir kurtarma işlemidir; kimseye yetki kazandırmamalı. Rolün
     * eski hâli gerekiyorsa Roller ekranından bilinçli olarak verilir.
     */
    public function down(): void
    {
        // Kasıtlı olarak boş.
    }
};
