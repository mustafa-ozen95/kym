<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hızlı Erişim salonları.
 *
 * Salon listesi kayıttır: salon panelden açılır, adı ve notu oradan değişir.
 * İki yerde sabit liste tutulsaydı ikisi ayrışabilir ve ekleme ekranında
 * görünüp sayfada çizilmeyen bir grup ortaya çıkardı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gruplar', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /* Kutucuk kaydının `grup` alanı bu anahtarı taşır. */
            $table->string('anahtar', 30)->unique();
            $table->string('ad');

            /* Başlığın yanındaki ince açıklama ("kendi ağımızda" gibi). */
            $table->string('not')->nullable();

            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
        });

        $this->mevcutSalonlar();
    }

    public function down(): void
    {
        Schema::dropIfExists('gruplar');
    }

    /**
     * Örnek salonlar.
     */
    private function mevcutSalonlar(): void
    {
        $salonlar = [
            ['anahtar' => 'ic', 'ad' => 'İç sistemler', 'not' => 'kendi ağımızda', 'sira' => 1],
            ['anahtar' => 'mon', 'ad' => 'Monitör', 'not' => 'canlı izleme ekranları', 'sira' => 2],
            ['anahtar' => 'cam', 'ad' => 'Kamera ve sensör', 'not' => 'veri merkezi içi görüntü ve ölçüm', 'sira' => 3],
            ['anahtar' => 'dis', 'ad' => 'Dış sistemler', 'not' => 'internet üzerinde', 'sira' => 4],
        ];

        // Örnek salonlar yalnızca örnek veri açıkken yazılır. Konteyner
        // kurulumunda kapalıdır: yeni bir kuruluma başkasının salonlarını
        // yazmak yanlış olurdu.
        if (! config('app.sample_data')) {
            return;
        }

        foreach ($salonlar as $salon) {
            DB::table('gruplar')->insert([
                ...$salon,
                'uuid' => (string) Str::uuid7(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
