<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Denetim kaydına satır yazar.
 *
 * Her satırın özeti BİR ÖNCEKİNİN özetini de kapsar. Böylece kaydı sonradan
 * düzenlemek tek satırı değiştirmekle bitmez; zincirin tamamı bozulur ve
 * doğrulama bunu görür. Anahtar uygulamanın kendi anahtarıdır — veritabanına
 * erişen biri zinciri yeniden üretemez.
 *
 * ZİNCİR KUYRUĞUNDAN KESİLEMEZ (ÇAPA). Satırlar birbirine bağlı ama zincirin
 * UCUNA ve UZUNLUĞUNA kimse bağlı değil: en yeni satırları ya da tüm tabloyu
 * silmek geriye kalan önekin kendi içinde tutarlı olmasını bozmaz. Bu yüzden
 * zincirin son özeti ve satır sayısı, activity_logs'un DIŞINDA (ayarlar
 * tablosunda) APP_KEY ile imzalı bir çapa olarak tutulur ve verify() uçta
 * ikisini de karşılaştırır.
 *
 * SINIRLAR (bilinçli, saklanmıyor):
 *  - Çapa ilk kayıtta/doğrulamada MEVCUT kuyruktan kurulur (canlı zincir
 *    bozulmasın diye). Çapa kurulmadan ÖNCE yapılmış bir kesme görülemez.
 *  - Çapa aynı veritabanında durur: hem satırları hem çapa satırını silebilen
 *    biri onu baştan kurdurabilir; ya da eski ama geçerli imzalı bir çapayı
 *    geri koyup tabloyu o noktaya kesebilir. Bundan güçlüsü zincir ucunu
 *    veritabanı DIŞINA (yalnızca eklenebilir bir kütüğe/uzak kayda) yazmaktır.
 *  - Tutarsız çapa kendiliğinden ONARILMAZ: kuyruk çapayla uyuşmuyorsa satır
 *    yine yazılır ama çapa ilerlemez, böylece verify() kırık göstermeye devam
 *    eder. Bilinçli bir kurtarma için ayarlar tablosundaki çapa satırı elle
 *    silinir (bir sonraki kayıt onu mevcut kuyruktan yeniden kurar).
 *
 * BİLİNEN ESKİ KIRIKLAR (MÜHÜR). Eski bir dönemde yazılmış
 * satırların özeti, nedeni belirlenemeden tutmayabilir. Satır silmek
 * çözüm değil: kalan ilk satır silinenin özetine bağlı, zincir yine kırık
 * görünür. Bunun yerine o anki kırık satırların parmak izi (satırın bütün
 * alanları + saklı özeti) imzalı olarak ayarlar tablosuna yazılır; verify()
 * mühürlü ve DEĞİŞMEMİŞ satırı kabul eder, yürüyüşe saklı özetiyle devam
 * eder. Mühürden sonra değişen mühürlü satır ya da yeni bir kırık yine
 * kırık görünür. Mühür satırları DEĞİŞTİRMEZ (bkz. kimlik:zincir-muhurle).
 */
class ActivityRecorder
{
    public function __construct(private Request $request) {}

    /** ayarlar tablosundaki çapa satırının anahtarı. */
    public const CAPA = 'denetim_zincir_capasi';

    /** ayarlar tablosundaki bilinen-eski-kırık mührünün anahtarı. */
    public const MUHUR = 'denetim_zincir_muhru';

    public function record(string $eylem, string $ozet, ?User $actor = null): ActivityLog
    {
        /*
         * Önceki özetin okunması, satırın yazılması ve çapanın ilerlemesi TEK
         * işlem ve çapa satırı KİLİTLİ: iki eşzamanlı kayıt aynı "önceki"yi
         * okuyup zinciri çatallayamasın.
         */
        return DB::transaction(function () use ($eylem, $ozet, $actor): ActivityLog {
            $capa = $this->capayiKilitle();

            $onceki = ActivityLog::query()->orderByDesc('id')->value('hash');

            $satir = [
                'user_id' => $actor?->getKey(),
                'actor_name' => $actor?->name,
                'eylem' => $eylem,
                'ozet' => $ozet,
                'ip' => $this->request->ip(),
                'onceki_hash' => $onceki,
                'created_at' => now(),
            ];

            $kayit = ActivityLog::create([...$satir, 'hash' => $this->hash($satir)]);

            /*
             * Çapa YALNIZCA tutarlıysa ilerler: kuyruk çapadan farklıysa
             * (kesilmiş, değiştirilmiş) ilerletmek kesmeyi sonradan "yasal"
             * göstermek olurdu.
             */
            if ($capa !== null && $capa['gecerli'] && $capa['head'] === $onceki) {
                $this->capayiYaz((string) $kayit->hash, $capa['n'] + 1);
            }

            return $kayit;
        });
    }

    /** @param  array<string, mixed>  $satir */
    private function hash(array $satir): string
    {
        return hash_hmac('sha256', json_encode([
            $satir['user_id'], $satir['actor_name'], $satir['eylem'], $satir['ozet'],
            $satir['ip'], $satir['onceki_hash'], (string) $satir['created_at'],
        ], JSON_UNESCAPED_UNICODE), $this->zincirAnahtari());
    }

    /**
     * Zincir bütün mü?
     *
     * @return array{ok: bool, checked: int, broken_id: ?int, muhurlu: int}
     */
    public function verify(): array
    {
        $muhur = $this->muhuruOku();
        $muhurlu = 0;

        /*
         * Çapa YÜRÜYÜŞTEN ÖNCE okunur: yürürken araya yeni kayıt girerse
         * tablo çapadan uzun olur — bu meşrudur (zincir geçerli sürdükçe
         * sonradan eklenen satırı anahtarsız kimse yazamaz). Kesme ise
         * çapanın gösterdiği SIRADAKİ satırı ya da sayıyı değiştirir.
         */
        $capa = $this->capayiOku();

        $onceki = null;
        $sayac = 0;
        $capadakiOzet = null;

        foreach (ActivityLog::query()->orderBy('id')->cursor() as $satir) {
            $sayac++;

            $beklenen = $this->hash([
                'user_id' => $satir->user_id, 'actor_name' => $satir->actor_name,
                'eylem' => $satir->eylem, 'ozet' => $satir->ozet, 'ip' => $satir->ip,
                'onceki_hash' => $onceki, 'created_at' => $satir->created_at,
            ]);

            if (! hash_equals($beklenen, (string) $satir->hash)) {
                if (! isset($muhur[$satir->id]) || ! hash_equals($muhur[$satir->id], $this->parmakIzi($satir))) {
                    return ['ok' => false, 'checked' => $sayac, 'broken_id' => $satir->id, 'muhurlu' => $muhurlu];
                }

                $muhurlu++;
            }

            $onceki = $satir->hash;

            if ($capa !== null && $sayac === $capa['n']) {
                $capadakiOzet = $onceki;
            }
        }

        if ($capa === null) {
            /* Çapa hiç kurulmamış (eski zincir): sağlam kuyruktan kurulur. */
            DB::transaction(fn () => $this->capayiKilitle());

            return ['ok' => true, 'checked' => $sayac, 'broken_id' => null, 'muhurlu' => $muhurlu];
        }

        /*
         * Satırlar kendi içinde tutarlı olsa bile kuyruktan kesilmiş ya da
         * boşaltılmış bir zincir burada yakalanır; kırık satır olmadığı için
         * broken_id boştur.
         */
        if (! $capa['gecerli'] || $sayac < $capa['n'] || $capadakiOzet !== $capa['head']) {
            return ['ok' => false, 'checked' => $sayac, 'broken_id' => null, 'muhurlu' => $muhurlu];
        }

        return ['ok' => true, 'checked' => $sayac, 'broken_id' => null, 'muhurlu' => $muhurlu];
    }

    /**
     * O anki kırık satırları imzalı mühre yazar; satırlara DOKUNMAZ.
     *
     * Yürüyüş verify() ile aynıdır ama kırıkta durmaz: kırık satırın saklı
     * özetiyle devam eder (sonraki satırlar ona bağlı). Yeniden çalıştırmak
     * mührü bugünkü kırıklarla DEĞİŞTİRİR.
     *
     * @return int mühürlenen satır sayısı
     */
    public function muhurle(string $not): int
    {
        $izler = [];
        $onceki = null;

        foreach (ActivityLog::query()->orderBy('id')->cursor() as $satir) {
            $beklenen = $this->hash([
                'user_id' => $satir->user_id, 'actor_name' => $satir->actor_name,
                'eylem' => $satir->eylem, 'ozet' => $satir->ozet, 'ip' => $satir->ip,
                'onceki_hash' => $onceki, 'created_at' => $satir->created_at,
            ]);

            if (! hash_equals($beklenen, (string) $satir->hash)) {
                $izler[(string) $satir->id] = $this->parmakIzi($satir);
            }

            $onceki = $satir->hash;
        }

        $govde = json_encode(['not' => $not, 'tarih' => now()->toIso8601String(), 'izler' => $izler], JSON_UNESCAPED_UNICODE);

        DB::table('ayarlar')->updateOrInsert(
            ['anahtar' => self::MUHUR],
            ['deger' => json_encode(['govde' => $govde, 'mac' => $this->muhurImzasi($govde)]), 'updated_at' => now()],
        );

        return count($izler);
    }

    /** Satırın bütün alanları + saklı özeti: mühürden sonra değişirse tutmaz. */
    private function parmakIzi(ActivityLog $satir): string
    {
        return hash_hmac('sha256', 'denetim-muhur|'.json_encode([
            $satir->id, $satir->user_id, $satir->actor_name, $satir->eylem, $satir->ozet,
            $satir->ip, $satir->onceki_hash, (string) $satir->created_at, $satir->hash,
        ], JSON_UNESCAPED_UNICODE), $this->zincirAnahtari());
    }

    private function muhurImzasi(string $govde): string
    {
        return hash_hmac('sha256', 'denetim-muhur-govde|'.$govde, $this->zincirAnahtari());
    }

    /** @return array<int, string> kimlik => parmak izi; imzasız/bozuk mühür boş sayılır. */
    private function muhuruOku(): array
    {
        $veri = json_decode((string) DB::table('ayarlar')->where('anahtar', self::MUHUR)->value('deger'), true);

        if (! is_array($veri) || ! is_string($veri['govde'] ?? null) || ! hash_equals($this->muhurImzasi($veri['govde']), (string) ($veri['mac'] ?? ''))) {
            return [];
        }

        $izler = json_decode($veri['govde'], true)['izler'] ?? [];

        return is_array($izler) ? array_map('strval', $izler) : [];
    }

    /** Zincir imza anahtarı: APP_KEY'den ayrı, boşsa APP_KEY (bkz. config/denetim.php). */
    private function zincirAnahtari(): string
    {
        return (string) config('denetim.zincir_anahtari');
    }

    /** Çapanın imzası: uç özet ve sayı birlikte, zincir anahtarıyla. */
    private function capaImzasi(?string $head, int $n): string
    {
        return hash_hmac('sha256', 'denetim-capa|'.$head.'|'.$n, $this->zincirAnahtari());
    }

    /** @return array{head: ?string, n: int, gecerli: bool}|null */
    private function capayiOku(bool $kilitle = false): ?array
    {
        $sorgu = DB::table('ayarlar')->where('anahtar', self::CAPA);

        if ($kilitle) {
            $sorgu->lockForUpdate();
        }

        $deger = $sorgu->value('deger');

        if ($deger === null) {
            return null;
        }

        $veri = json_decode((string) $deger, true);

        if (! is_array($veri) || ! array_key_exists('head', $veri) || ! is_int($veri['n'] ?? null)) {
            return ['head' => null, 'n' => 0, 'gecerli' => false];
        }

        $head = $veri['head'] === null ? null : (string) $veri['head'];

        return [
            'head' => $head,
            'n' => $veri['n'],
            'gecerli' => hash_equals($this->capaImzasi($head, $veri['n']), (string) ($veri['mac'] ?? '')),
        ];
    }

    private function capayiYaz(?string $head, int $n): void
    {
        DB::table('ayarlar')->updateOrInsert(
            ['anahtar' => self::CAPA],
            [
                'deger' => json_encode(['head' => $head, 'n' => $n, 'mac' => $this->capaImzasi($head, $n)]),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Çapa satırını kilitleyerek okur; yoksa MEVCUT kuyruktan kurar.
     *
     * Satır önce (yoksa) boş bir yer tutucuyla eklenir ki kilitlenecek bir
     * şey olsun ve iki eşzamanlı ilk kayıt çakışmasın; asıl değer kilit
     * alındıktan sonra yazılır.
     *
     * @return array{head: ?string, n: int, gecerli: bool}
     */
    private function capayiKilitle(): array
    {
        DB::table('ayarlar')->insertOrIgnore([
            'anahtar' => self::CAPA, 'deger' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $capa = $this->capayiOku(kilitle: true);

        /* Yer tutucu (değer boş) = hiç kurulmamış: mevcut kuyruktan kur. */
        if ($capa === null) {
            $head = ActivityLog::query()->orderByDesc('id')->value('hash');
            $n = ActivityLog::query()->count();

            $this->capayiYaz($head, $n);

            return ['head' => $head, 'n' => $n, 'gecerli' => true];
        }

        return $capa;
    }
}
