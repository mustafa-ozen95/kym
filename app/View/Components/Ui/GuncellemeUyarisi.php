<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Services\Guncelleme\GuncellemeDenetimi;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Yeni sürüm varken güncelleme yetkisi olana (ayar.guncelleme) uyarı. Karar burada; şablon yalnızca hazır
 * durumu çizer.
 */
class GuncellemeUyarisi extends Component
{
    public bool $goster;

    public int $geride = 0;

    public function __construct(GuncellemeDenetimi $denetim)
    {
        $this->goster = Gate::allows('ayar.guncelleme') && $denetim->yeniVar();

        if ($this->goster) {
            $this->geride = (int) ($denetim->son()['geride'] ?? 0);
        }
    }

    public function render(): View
    {
        return view('components.ui.guncelleme-uyarisi');
    }
}
