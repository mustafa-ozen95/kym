<?php

declare(strict_types=1);

use App\Enums\BadgeVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Yerleşik roller bağlı panellerle AYNI adlara geçer.
 *
 * Merkeze özgü eski adlar (kullanici-yoneticisi, editor, uye) yerini ortak
 * adlara bırakır: aynı kişi her panelde aynı rol adını görmeli. Rol bir
 * kişiyi tanımlıyor —
 * "teknik", "network", "muhasebe" — ve o kişi her yerde aynı kişi.
 *
 * ADMIN KORUNUR: hesaplar ona bağlı ve en az bir yönetici rolü kalmak zorunda.
 * Eski rollerdeki hesaplar TEKNİK'e taşınır; yetkisi genişleyen kimse olmasın
 * diye en dar rol seçilir — eksik veri yetki üretmemeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sablonlar = (array) config('permissions.templates', []);

        $yeniler = [
            [
                'slug' => 'network', 'label' => 'Network', 'badge' => BadgeVariant::Blue->value,
                'position' => 1,
                'description' => 'Hızlı Erişim kutucuklarını ve salonlarını yönetir; hesaplara ve ayarlara dokunmaz.',
            ],
            [
                'slug' => 'teknik', 'label' => 'Teknik', 'badge' => BadgeVariant::Gray->value,
                'position' => 2, 'is_default' => true,
                'description' => 'Neyin nerede olduğunu görür, değiştirmez.',
            ],
            [
                'slug' => 'muhasebe', 'label' => 'Muhasebe', 'badge' => BadgeVariant::Cyan->value,
                'position' => 3,
                'description' => 'Merkezin yönetimine girmez; yalnızca kendisine açılan kutucukları görür.',
            ],
        ];

        // Örnek veri kapalıyken bu roller hiç açılmaz.
        if (! config('app.ornek_veri')) {
            $yeniler = [];
        }

        foreach ($yeniler as $rol) {
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

        $this->eskileriTasi();
    }

    public function down(): void
    {
        /*
         * Geri alırken hesaplar ADMIN'e DEĞİL, en dar role taşınır. Geri alma
         * bir kurtarma işlemidir; kimseye yetki kazandırmamalı.
         */
        $uye = DB::table('roller')->where('slug', 'uye')->value('id');

        if ($uye !== null) {
            DB::table('users')
                ->whereIn('rol_id', DB::table('roller')->whereIn('slug', ['network', 'teknik', 'muhasebe'])->pluck('id'))
                ->update(['rol_id' => $uye]);
        }

        DB::table('roller')->whereIn('slug', ['network', 'teknik', 'muhasebe'])->delete();
    }

    /**
     * Eski rollerdeki hesapları taşır, sonra o rolleri siler.
     *
     * Sıra önemli: rol silinmeden önce hesapların taşınması gerekiyor, yoksa
     * yabancı anahtar kısıtı düşer ve göç yarıda kalır.
     */
    private function eskileriTasi(): void
    {
        $eskiler = ['uye', 'editor', 'kullanici-yoneticisi'];
        $teknik = DB::table('roller')->where('slug', 'teknik')->value('id');

        DB::table('users')
            ->whereIn('rol_id', DB::table('roller')->whereIn('slug', $eskiler)->pluck('id'))
            ->update(['rol_id' => $teknik]);

        DB::table('roller')->whereIn('slug', $eskiler)->delete();
    }
};
