<?php

declare(strict_types=1);

namespace App\View\Components\Form;

use App\Services\PasswordRule;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Parola kurallarını taşıyan "?" imi.
 *
 * Kurallar ekranda sürekli durmaz: yer kaplıyor ve çoğu zaman zaten
 * karşılanmış şeyleri tekrarlıyordu. Üzerine gelen ya da odaklanan görür.
 */
class PasswordHint extends Component
{
    public string $metin;

    public function __construct()
    {
        $this->metin = collect(PasswordRule::olcutler())
            ->reject(fn (array $olcut): bool => $olcut['anahtar'] === 'eslesme')
            ->pluck('metin')
            ->implode(' · ');
    }

    public function render(): View
    {
        return view('components.form.password-hint');
    }
}
