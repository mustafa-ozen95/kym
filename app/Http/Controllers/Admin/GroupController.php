<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GroupRequest;
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
 * Hızlı Erişim salonları.
 *
 * Salon listesi iki yerde birden sabitti (çizim betiği ve ayar dosyası); yeni
 * bir salon açmak iki dosyayı düzenlemek demekti ve ikisi ayrışırsa ekleme
 * ekranında görünüp sayfada çizilmeyen bir grup ortaya çıkıyordu.
 *
 * SALON KENDİ SAYFASI OLABİLİR (ör. "Salon A"): ayrı sayfa işaretli bir
 * salon, şifre yerine yetki sorar.
 */
class GroupController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:grup.gor'], only: ['index']),
            new Middleware(['auth', 'can:grup.ekle'], only: ['store']),
            new Middleware(['auth', 'can:grup.duzenle'], only: ['update', 'reorder']),
            new Middleware(['auth', 'can:grup.sil'], only: ['destroy']),
        ];
    }

    public function index(Request $request, #[CurrentUser] $kullanici): View
    {
        return view('admin.groups', [
            /* N+1: her satır kaç kutucuğu olduğunu yazıyor. */
            'gruplar' => Group::withCount('sistemler')->sirali()->get(),
            'ekleyebilir' => $kullanici->can('grup.ekle'),
            'duzenleyebilir' => $kullanici->can('grup.duzenle'),
            'silebilir' => $kullanici->can('grup.sil'),

            /* Pencerenin ADRESİ vardır; doğrulama hatasında açık geri gelir. */
            'window' => $request->query('window') === 'add' ? 'add' : null,
            'duzenlenen' => $request->query('record')
                ? Group::firstWhere('uuid', $request->query('record'))
                : null,
        ]);
    }

    public function store(GroupRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $group = Group::create($request->validated());

        $recorder->record('grup-eklendi', $group->ad.' salonu açıldı', $kullanici);

        return redirect()->route('admin.groups')->with('status', $group->ad.' salonu açıldı.');
    }

    public function update(GroupRequest $request, Group $group, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validated();

        /*
         * ANAHTAR DEĞİŞİRSE kutucuklar da taşınır.
         *
         * Kutucuk kaydı salonu anahtarıyla tanıyor; yalnızca salonu
         * güncellemek, içindeki her kutucuğu sahipsiz bırakır ve hepsi
         * sayfadan düşerdi.
         */
        if (($veri['anahtar'] ?? $group->anahtar) !== $group->anahtar) {
            System::where('grup', $group->anahtar)->update(['grup' => $veri['anahtar']]);
        }

        $group->update($veri);

        $recorder->record('grup-guncellendi', $group->ad.' salonu güncellendi', $kullanici);

        return redirect()->route('admin.groups')->with('status', $group->ad.' salonu güncellendi.');
    }

    /**
     * Salonları sürükleyerek sıralar.
     *
     * Sayfadaki bölüm dizilimi bu sıraya bakıyor; sayı kutusuna elle yazmak
     * sırayı değiştirmenin dolambaçlı yoluydu.
     */
    public function reorder(Request $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string'],
        ]);

        $salonlar = Group::pluck('id', 'uuid');
        $numara = 1;

        DB::transaction(function () use ($veri, $salonlar, &$numara): void {
            foreach ($veri['order'] as $uuid) {
                if ($salonlar->has($uuid)) {
                    Group::whereKey($salonlar[$uuid])->update(['sira' => $numara++]);
                }
            }
        });

        $recorder->record('grup-siralandi', 'Salonlar yeniden sıralandı', $kullanici);

        return back()->with('status', 'Salon sırası güncellendi.');
    }

    public function destroy(Group $group, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /*
         * Kutucuğu olan salon silinmez.
         *
         * Silinseydi içindeki kutucuklar var olmayan bir salona bakar ve
         * sayfadan sessizce düşerdi — kimse silindiklerini söylemeden.
         */
        if ($group->sistemler()->exists()) {
            return back()->with('error', 'Bu salonda kutucuklar var; önce onları başka bir salona taşıyın.');
        }

        $ad = $group->ad;
        $group->delete();

        $recorder->record('grup-silindi', $ad.' salonu silindi', $kullanici);

        return redirect()->route('admin.groups')->with('status', $ad.' salonu silindi.');
    }
}
