<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sistem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kutucuğun çizim için ihtiyaç duyduğu alanlar.
 *
 * ANAHTAR ADLARI İNGİLİZCE: çizim betiğinin (elle çizilmiş SVG animasyonlar)
 * beklediği adlarla birebir aynıdır.
 *
 * Kabin kimliği degrade tanımlarını benzersiz kılar; kayıtta yoksa kaydın
 * kendisinden türetilir, böylece yeni eklenen sistem de çakışmaz.
 */
class SistemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Sistem $sistem */
        $sistem = $this->resource;

        return [
            'name' => $sistem->ad,
            'desc' => $sistem->aciklama,
            'url' => $sistem->adres ?: '#',
            'color' => $sistem->renk,
            'code' => $sistem->kod,
            'strip' => $sistem->serit,
            'group' => $sistem->grup,
            /*
             * Kabin çizimi sabit çizim kümesinden biri (k1…k13). Kayıtta yoksa
             * kaydın kendisinden türetilir ve her zaman var olan bir çizime
             * düşer; çizimi olmayan bir ad çizim betiğini durdururdu.
             *
             * Türetme UUID'den yapılır, sıralı anahtardan DEĞİL: sıralı
             * anahtardan türeyen değer dışarıya kaydın kaçıncı sırada
             * açıldığını sızdırır ve kabinler ekleme sırasına göre
             * dizilirdi. UUID'den türeyen değer kayda özel, kararlı ve
             * sırasızdır.
             */
            'cab' => $sistem->kabin ?: 'k'.((crc32((string) $sistem->uuid) % 13) + 1),
            ...$this->yan($sistem),
        ];
    }

    /** @return array<string, array{label: string, url: string}> */
    private function yan(Sistem $sistem): array
    {
        if (blank($sistem->yan_etiket) || blank($sistem->yan_adres)) {
            return [];
        }

        return ['side' => ['label' => $sistem->yan_etiket, 'url' => $sistem->yan_adres]];
    }
}
