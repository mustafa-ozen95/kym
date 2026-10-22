<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bilinen eski kırık mührü: mühürlü ve değişmemiş satır kabul edilir;
 * mühürden sonra değişen satır ya da yeni kırık yine kırıktır.
 */
final class ChainSealTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: ActivityRecorder, 1: list<ActivityLog>} */
    private function zincir(): array
    {
        $recorder = app(ActivityRecorder::class);
        $kisi = User::factory()->rol('admin')->create(['durum' => Status::Etkin]);
        $satirlar = [];

        foreach (['bir', 'iki', 'uc', 'dort'] as $ozet) {
            $satirlar[] = $recorder->record('deneme', $ozet, $kisi);
        }

        return [$recorder, $satirlar];
    }

    public function test_muhurlu_kirik_kabul_edilir_satir_degismez(): void
    {
        [$recorder, $s] = $this->zincir();
        ActivityLog::whereKey($s[1]->id)->update(['ozet' => 'iki (eski bozukluk)']);
        $this->assertFalse($recorder->verify()['ok']);

        $this->artisan('identity:chain-seal', ['--confirm' => true])->assertSuccessful();

        $sonuc = $recorder->verify();
        $this->assertTrue($sonuc['ok'], json_encode($sonuc));
        $this->assertSame(1, $sonuc['muhurlu']);
        $this->assertSame('iki (eski bozukluk)', ActivityLog::find($s[1]->id)->ozet, 'mühür satıra dokunmaz');
        $this->assertTrue(ActivityLog::where('eylem', 'zincir-muhurlendi')->exists());
    }

    public function test_muhurden_sonra_degisen_muhurlu_satir_kiriktir(): void
    {
        [$recorder, $s] = $this->zincir();
        ActivityLog::whereKey($s[1]->id)->update(['ozet' => 'iki (eski bozukluk)']);
        $recorder->muhurle('test');

        ActivityLog::whereKey($s[1]->id)->update(['ozet' => 'iki (yeniden oynandi)']);

        $sonuc = $recorder->verify();
        $this->assertFalse($sonuc['ok']);
        $this->assertSame($s[1]->id, $sonuc['broken_id']);
    }

    public function test_muhurden_sonra_yeni_kirik_gorunur(): void
    {
        [$recorder, $s] = $this->zincir();
        ActivityLog::whereKey($s[1]->id)->update(['ozet' => 'iki (eski bozukluk)']);
        $recorder->muhurle('test');

        ActivityLog::whereKey($s[2]->id)->update(['ozet' => 'uc (yeni)']);

        $this->assertSame($s[2]->id, $recorder->verify()['broken_id']);
    }

    public function test_imzasi_bozuk_muhur_yok_sayilir(): void
    {
        [$recorder, $s] = $this->zincir();
        ActivityLog::whereKey($s[1]->id)->update(['ozet' => 'iki (eski bozukluk)']);
        $recorder->muhurle('test');

        $deger = json_decode((string) DB::table('ayarlar')->where('anahtar', ActivityRecorder::SEAL)->value('deger'), true);
        $deger['govde'] = str_replace('test', 'oynanmis', $deger['govde']);
        DB::table('ayarlar')->where('anahtar', ActivityRecorder::SEAL)->update(['deger' => json_encode($deger)]);

        $this->assertSame($s[1]->id, $recorder->verify()['broken_id']);
    }
}
