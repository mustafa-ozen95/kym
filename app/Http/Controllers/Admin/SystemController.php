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
            new Middleware(['auth', 'can:sistem.duzenle'], only: ['update', 'sirala']),
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

        $duzenlenen = $request->filled('kayit')
            ? System::where('uuid', $request->query('kayit'))->first()
            : null;

        $pencere = $request->query('pencere');

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
            'pencereAcik' => $pencere === 'ekle' || ($pencere === 'duzenle' && $duzenlenen !== null),
            'pencereHedefi' => $duzenlenen
                ? route('yonetim.sistemler.guncelle', $duzenlenen)
                : route('yonetim.sistemler.ekle'),
            ...$this->secenekler(),
        ]);
    }

    public function store(SystemRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $sistem = System::create($request->kayit());

        $recorder->record('sistem-eklendi', $sistem->ad.' kutucuğu eklendi', $kullanici);

        return redirect()->route('yonetim.sistemler')->with('status', $sistem->ad.' eklendi.');
    }

    public function update(SystemRequest $request, System $sistem, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $sistem->update($request->kayit());

        $recorder->record('sistem-guncellendi', $sistem->ad.' kutucuğu güncellendi', $kullanici);

        return redirect()->route('yonetim.sistemler')->with('status', $sistem->ad.' güncellendi.');
    }

    public function destroy(System $sistem, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $ad = $sistem->ad;
        $sistem->delete();

        $recorder->record('sistem-silindi', $ad.' kutucuğu silindi', $kullanici);

        return redirect()->route('yonetim.sistemler')->with('status', $ad.' silindi.');
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
    public function sirala(Request $request, Group $grup, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            'sira' => ['required', 'array'],
            'sira.*' => ['string'],
        ]);

        $kutucuklar = System::where('grup', $grup->anahtar)->pluck('id', 'uuid');
        $numara = 1;

        DB::transaction(function () use ($veri, $kutucuklar, &$numara): void {
            foreach ($veri['sira'] as $uuid) {
                if ($kutucuklar->has($uuid)) {
                    System::whereKey($kutucuklar[$uuid])->update(['sira' => $numara++]);
                }
            }
        });

        $recorder->record('sistem-siralandi', $grup->ad.' salonundaki kutucuklar yeniden sıralandı', $kullanici);

        return back()->with('status', $grup->ad.' salonunun sırası güncellendi.');
    }

    /** @return array<string, array<string, string>> */
    private function secenekler(): array
    {
        return [
            'gruplar' => Group::secenekler(),
            'seritler' => config('system.seritler'),
        ];
    }
}
