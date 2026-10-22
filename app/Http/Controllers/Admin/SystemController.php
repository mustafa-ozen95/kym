<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemRequest;
use App\Models\Group;
use App\Models\System;
use App\Services\ActivityRecorder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hızlı Erişim kutucuklarını yönetir.
 *
 * Bu ekran, listeyi koddan çıkarmanın asıl sebebi: bir sistem eklemek ya da
 * kaldırmak dosya düzenlemeyi değil, formu doldurmayı gerektirir.
 *
 * Her değişiklik denetim kaydına yazılır; kutucuklar sayfanın kapısı ve
 * "bu adres oraya ne zaman kondu" sorusunun yanıtı durmalı.
 */
class SystemController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:sistem.gor'], only: ['index']),
            new Middleware(['auth', 'can:sistem.ekle'], only: ['store']),
            /* Sıralama bir düzenlemedir (GroupController::sirala da grup.duzenle ister). */
            new Middleware(['auth', 'can:sistem.duzenle'], only: ['update', 'reorder']),
            new Middleware(['auth', 'can:sistem.sil'], only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        /*
         * Kutucuklar SALON SALON listelenir.
         *
         * Tek düz tabloda hangi kutucuğun hangi bölümde durduğu ancak "Grup"
         * sütunu okunarak anlaşılırdı; sayfadaki yerleşim ise salon
         * sırasıyla. Ekran sayfayla aynı düzeni gösterir.
         *
         * Salonu olmayan kutucuk da GÖRÜNMELİ: silinmiş bir salonun ardında
         * kalan kayıt, listede hiç çıkmasaydı kimse düzeltemezdi.
         */
        $salonlar = Group::sirali()->get();
        $sistemler = System::query()->sirali()->get();

        $duzenlenen = $request->filled('record')
            ? System::where('uuid', $request->query('record'))->first()
            : null;

        $window = $request->query('window');

        return view('admin.systems', [
            'salonlar' => $salonlar,
            'salonaGore' => $sistemler->groupBy('grup'),
            'sahipsiz' => $sistemler->whereNotIn('grup', $salonlar->pluck('anahtar'))->values(),
            'duzenlenen' => $duzenlenen,

            /*
             * Pencere kararı ve form hedefi HAZIR gider: düzenleme kaydın
             * kendi değerleriyle, ekleme boş açılır. Blade bunları kendi
             * hesaplıyordu.
             */
            'windowOpen' => $window === 'add' || ($window === 'edit' && $duzenlenen !== null),
            'windowTarget' => $duzenlenen
                ? route('admin.systems.update', $duzenlenen)
                : route('admin.systems.store'),
            ...$this->secenekler(),
        ]);
    }

    public function store(SystemRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $system = System::create($request->kayit());

        $recorder->record('sistem-eklendi', $system->ad.' kutucuğu eklendi', $kullanici);

        return redirect()->route('admin.systems')->with('status', $system->ad.' eklendi.');
    }

    public function update(SystemRequest $request, System $system, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $system->update($request->kayit());

        $recorder->record('sistem-guncellendi', $system->ad.' kutucuğu güncellendi', $kullanici);

        return redirect()->route('admin.systems')->with('status', $system->ad.' güncellendi.');
    }

    public function destroy(System $system, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $ad = $system->ad;
        $system->delete();

        $recorder->record('sistem-silindi', $ad.' kutucuğu silindi', $kullanici);

        return redirect()->route('admin.systems')->with('status', $ad.' silindi.');
    }

    /**
     * Kutucukları KENDİ SALONU İÇİNDE sıralar.
     *
     * Sayfa genelinde tek bir sıra (10, 20, 30…) olsaydı bir kutucuğu iki
     * sıra yukarı almak, aradaki her sayıyı elle düzeltmek demek olurdu. Her
     * salon 1'den başlar ve sıra sürükleyerek verilir.
     *
     * Gelen liste O SALONA ait olmayan bir kutucuk taşıyorsa yok sayılır:
     * gövdeye elle satır ekleyen biri, göremediği bir salonun dizilimini
     * bozamamalı.
     */
    public function reorder(Request $request, Group $group, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string'],
        ]);

        $kutucuklar = System::where('grup', $group->anahtar)->pluck('id', 'uuid');
        $numara = 1;

        DB::transaction(function () use ($veri, $kutucuklar, &$numara): void {
            foreach ($veri['order'] as $uuid) {
                if ($kutucuklar->has($uuid)) {
                    System::whereKey($kutucuklar[$uuid])->update(['sira' => $numara++]);
                }
            }
        });

        $recorder->record('sistem-siralandi', $group->ad.' salonundaki kutucuklar yeniden sıralandı', $kullanici);

        return back()->with('status', $group->ad.' salonunun sırası güncellendi.');
    }

    /** @return array<string, array<string, string>> */
    private function secenekler(): array
    {
        return [
            'gruplar' => Group::secenekler(),
            'seritler' => config('system.lanes'),
        ];
    }
}
