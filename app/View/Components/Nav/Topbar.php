<?php

declare(strict_types=1);

namespace App\View\Components\Nav;

use App\Services\Yetki\YonetimBolumleri;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Panelin üst menüsü — bağlı panellerle aynı görünüm ve aynı kural.
 *
 * Menüde yalnızca kullanıcının erişebildiği bölümler görünür: erişemeyeceği
 * bir bağlantıyı göstermek 403 üretmekten başka işe yaramaz.
 */
class Topbar extends Component
{
    public string $initials;

    /** @var array<int, array{label: string, url: string, active: bool}> */
    public array $sections;

    /** @var array<int, array{label: string, url?: string}> */
    public array $crumbs;

    /** @param  array<int, array{label: string, url?: string}>  $crumbs */
    public function __construct(array $crumbs = [])
    {
        $this->initials = self::initialsFor(auth()->user()?->name);
        $this->sections = $this->visibleSections();
        $this->crumbs = count($crumbs) < 2 ? [] : array_values($crumbs);
    }

    /** @return array<int, array{label: string, url: string, active: bool}> */
    private function visibleSections(): array
    {
        $ad = Route::currentRouteName() ?? '';

        /* Liste ve yetkileri YonetimBolumleri'nde; iki yerde tutulmaz. */
        return array_map(fn (array $bolum): array => [
            'label' => $bolum['label'],
            'url' => $bolum['url'],
            'active' => $ad === $bolum['route'],
        ], app(YonetimBolumleri::class)->gorunen());
    }

    /** Adın baş harfleri; avatar yerine geçer. */
    private static function initialsFor(?string $ad): string
    {
        $parcalar = preg_split('/\s+/', trim((string) $ad)) ?: [];
        $harfler = array_map(fn (string $p): string => Str::upper(Str::substr($p, 0, 1)), array_filter($parcalar));

        return implode('', array_slice($harfler, 0, 2)) ?: '?';
    }

    public function render(): View
    {
        return view('components.nav.topbar');
    }
}
