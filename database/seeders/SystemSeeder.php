<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\System;
use Illuminate\Database\Seeder;

/**
 * Kutucuk listesini YEREL BİR DOSYADAN kurar.
 *
 * VERİ DEPODA DURMAZ. Kutucuk listesi kurumun iç sistemlerinin adlarını,
 * açıklamalarını ve iç ağ adreslerini taşır; depoya girseydi klonlayan
 * herkes görürdü. Yazılım depoya girer, veri girmez.
 *
 * `database/seeders/local/systems.php` varsa oradan okunur (dosya
 * .gitignore'dadır); yoksa HİÇBİR ŞEY YAPILMAZ. Uydurma bir liste yazmak da
 * doğru olmazdı: yeni bir kuruluma ait olmayan kayıtlar bırakırdı. Kutucuklar
 * zaten ekrandan eklenir.
 *
 * Dosyanın şekli: local/README.md
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $dosya = __DIR__.'/local/systems.php';

        if (! is_file($dosya)) {
            $this->command?->warn('local/systems.php yok; kutucuk eklenmedi.');

            return;
        }

        /** @var array<int, array<string, mixed>> $sistemler */
        $sistemler = require $dosya;

        foreach ($sistemler as $sistem) {
            System::updateOrCreate(['kod' => $sistem['kod']], $sistem);
        }
    }
}
