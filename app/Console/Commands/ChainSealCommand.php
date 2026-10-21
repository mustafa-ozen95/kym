<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityRecorder;
use Illuminate\Console\Command;

/**
 * Bilinen eski zincir kırıklarını mühürler (bkz. ActivityRecorder::muhurle).
 *
 * Satırlara DOKUNMAZ; yalnızca o anki kırık satırların parmak izini imzalı
 * olarak ayarlar tablosuna yazar. Yeniden çalıştırmak mührü bugünkü kırıkla
 * değiştirir; bu yüzden onay istenir.
 */
class ChainSealCommand extends Command
{
    protected $signature = 'kimlik:zincir-muhurle {--onay : Mührü yaz (yoksa yalnızca durumu gösterir)}';

    protected $description = 'Bilinen eski denetim zinciri kırıklarını imzalı mühürle dondurur';

    public function handle(ActivityRecorder $recorder): int
    {
        if (! $this->option('onay')) {
            $sonuc = $recorder->verify();
            $this->line($sonuc['ok'] ? 'Zincir bütün; mühre gerek yok.' : 'Zincir kırık: ilk bozuk kayıt #'.($sonuc['broken_id'] ?? '-').'.');
            $this->line('Yazmak için: kimlik:zincir-muhurle --onay');

            return self::SUCCESS;
        }

        $adet = $recorder->muhurle('Bilinen eski kırıklar; satırlar değiştirilmedi');
        $recorder->record('zincir-muhurlendi', $adet.' bilinen eski kırık satır mühürlendi (satırlar değiştirilmedi)');

        $this->info("{$adet} satır mühürlendi.");

        return self::SUCCESS;
    }
}
