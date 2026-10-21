<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Enums\BadgeVariant;
use App\Enums\Durum;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Durum noktası ve etiketi. Renk eşlemesi enum'un kendi sorumluluğundadır.
 *
 * Bu projede hesabın durumu `Durum` enum'udur.
 */
class StatusDot extends Component
{
    public BadgeVariant $variant;

    public string $label;

    public function __construct(
        Durum $status,
        public bool $showLabel = true,
    ) {
        $this->variant = $status->badge();
        $this->label = $status->label();
    }

    public function render(): View
    {
        return view('components.ui.status-dot');
    }
}
