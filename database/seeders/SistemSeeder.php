<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sistem;
use Illuminate\Database\Seeder;

/**
 * Kutucuk listesini YEREL BİR DOSYADAN kurar.
 *
 * VERİ DEPODA DURMAZ. Kutucuk listesi kurumun iç sistemlerinin adlarını,
 * açıklamalarını ve iç ağ adreslerini taşır; depoya girseydi klonlayan
 * herkes görürdü. Yazılım depoya girer, veri girmez.
 *
 * `database/seeders/yerel/sistemler.php` varsa oradan okunur (dosya
 * .gitignore'dadır); yoksa HİÇBİR ŞEY YAPILMAZ. Uydurma bir liste yazmak da
 * doğru olmazdı: yeni bir kuruluma ait olmayan kayıtlar bırakırdı. Kutucuklar
 * zaten ekrandan eklenir.
 *
 * Dosyanın şekli: yerel/OKUBENI.md
 */
class SistemSeeder extends Seeder
{
    public function run(): void
    {
        $dosya = __DIR__.'/yerel/sistemler.php';

        if (! is_file($dosya)) {
            $this->command?->warn('yerel/sistemler.php yok; kutucuk eklenmedi.');

            return;
        }

        /** @var array<int, array<string, mixed>> $sistemler */
        $sistemler = require $dosya;

        foreach ($sistemler as $sistem) {
            Sistem::updateOrCreate(['kod' => $sistem['kod']], $sistem);
        }
    }
}
