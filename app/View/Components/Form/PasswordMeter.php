<?php

declare(strict_types=1);

namespace App\View\Components\Form;

use App\Services\PasswordRule;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Parolada EKSİK olanın canlı uyarısı.
 *
 * Önce bütün ölçütler liste hâlinde duruyordu; dört satır, üstelik üçü zaten
 * karşılanmışken de ekranda. Kullanıcıyı ilgilendiren tek şey NEYİN EKSİK
 * olduğu: karşılanan ölçüt sessizce kaybolur, kural metinleri "?" ipucunda
 * durur (bkz. PasswordHint).
 *
 * Kuralı bileşen bilmez, sorar (PasswordRule): metinler sunucudan gelir,
 * tarayıcı yalnızca hangisinin eksik olduğunu seçer. Kabul kararı sunucunun.
 */
class PasswordMeter extends Component
{
    /** @var array<string, string> */
    public array $olcutler;

    public int $uzunluk;

    public function __construct(public string $alan, public ?string $tekrar = null)
    {
        $this->olcutler = collect(PasswordRule::olcutler())
            ->when($tekrar === null, fn ($hepsi) => $hepsi->reject(
                fn (array $olcut): bool => $olcut['anahtar'] === 'eslesme',
            ))
            ->mapWithKeys(fn (array $olcut): array => [$olcut['anahtar'] => $olcut['metin']])
            ->all();

        $this->uzunluk = PasswordRule::uzunluk();
    }

    public function render(): View
    {
        return view('components.form.password-meter');
    }
}
