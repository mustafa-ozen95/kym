<?php

declare(strict_types=1);

namespace App\View\Components\Form;

use App\Services\ParolaKurali;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Parolada EKSİK olanın canlı uyarısı.
 *
 * Önce bütün ölçütler liste hâlinde duruyordu; dört satır, üstelik üçü zaten
 * karşılanmışken de ekranda. Kullanıcıyı ilgilendiren tek şey NEYİN EKSİK
 * olduğu: karşılanan ölçüt sessizce kaybolur, kural metinleri "?" ipucunda
 * durur (bkz. ParolaIpucu).
 *
 * Kuralı bileşen bilmez, sorar (ParolaKurali): metinler sunucudan gelir,
 * tarayıcı yalnızca hangisinin eksik olduğunu seçer. Kabul kararı sunucunun.
 */
class ParolaOlcut extends Component
{
    /** @var array<string, string> */
    public array $olcutler;

    public int $uzunluk;

    public function __construct(public string $alan, public ?string $tekrar = null)
    {
        $this->olcutler = collect(ParolaKurali::olcutler())
            ->when($tekrar === null, fn ($hepsi) => $hepsi->reject(
                fn (array $olcut): bool => $olcut['anahtar'] === 'eslesme',
            ))
            ->mapWithKeys(fn (array $olcut): array => [$olcut['anahtar'] => $olcut['metin']])
            ->all();

        $this->uzunluk = ParolaKurali::uzunluk();
    }

    public function render(): View
    {
        return view('components.form.parola-olcut');
    }
}
