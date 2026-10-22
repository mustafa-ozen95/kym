<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BadgeVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Services\ActivityRecorder;
use App\Services\Permission\PermissionDictionary;
use App\Services\Permission\PermissionResolver;
use App\Services\Permission\SettingSections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Roller ve hazır yetki şablonları — Ayarlar'ın bir SEKMESİ.
 *
 * LİSTE ve AYRINTI AYRI SAYFA. Hepsi tek sayfada açık olsa her rol yan yana
 * uzun bir form olurdu ve hangi kutucuğun hangi role ait olduğu kaybolurdu.
 *
 * Rol bir ŞABLONDUR: kutucuklar işaretlenir, o rolü taşıyan herkes o
 * yetkileri alır. Kişiye özel istisnalar kullanıcı ekranından verilir.
 *
 * YERLEŞİK ROLLER SİLİNMEZ ve slug'ları değişmez; göç, testler ve şablon
 * dosyası onlara adıyla atıfta bulunuyor. Şablonları düzenlenebilir —
 * kurulum büyüdükçe "Editör"ün ne yapabildiği değişebilir.
 */
class RoleController extends Controller implements HasMiddleware
{
    public function __construct(
        private PermissionDictionary $sozluk,
        private SettingSections $bolumler,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:rol.gor'], only: ['index', 'show']),
            new Middleware(['auth', 'can:rol.duzenle'], only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(#[CurrentUser] $kullanici): View
    {
        return view('admin.roles', [
            'serit' => $this->bolumler->serit(),

            /* N+1: her satır kaç kullanıcısı olduğunu yazıyor. */
            'roller' => Role::withCount('kullanicilar')->sirali()->get(),
            'rozetler' => $this->rozetler(),
            'duzenleyebilir' => $kullanici->can('rol.duzenle'),
        ]);
    }

    public function show(Role $role, #[CurrentUser] $kullanici): View
    {
        return view('admin.role', [
            'serit' => $this->bolumler->serit(),
            'rol' => $role,
            'kaynaklar' => $this->sozluk->ekranSatirlari(PermissionResolver::yetkiDagitilabilirMi()),
            'sablon' => $role->yetenekler(),
            'rozetler' => $this->rozetler(),
            'kullaniciSayisi' => $role->kullanicilar()->count(),
            'duzenleyebilir' => $kullanici->can('rol.duzenle'),

            /*
             * Yetki dağıtımı açık mı? Kapalıyken ekran kutucuk göstermez;
             * işaretlenip hiçbir şey açmayan bir kutu, işaretleyene yalan
             * söyler (bkz. PermissionResolver).
             */
            'yetkiDagitilabilir' => PermissionResolver::yetkiDagitilabilirMi(),
        ]);
    }

    /** @return array<string, string> */
    private function rozetler(): array
    {
        return collect(BadgeVariant::cases())
            ->mapWithKeys(fn (BadgeVariant $rozet): array => [$rozet->value => $rozet->value])
            ->all();
    }

    public function store(RoleRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Yönetici bayrağı yalnızca yöneticide: bkz. update(). */
        abort_if($request->boolean('is_admin') && ! $kullanici->yonetici(), 403);

        $role = Role::create($this->veri($request));

        $this->varsayilaniTekleştir($role);

        $recorder->record('rol-eklendi', $role->label.' rolü açıldı', $kullanici);

        return redirect()->route('admin.roles.show', $role)
            ->with('status', $role->label.' rolü açıldı; yetkilerini şimdi seçebilirsiniz.');
    }

    public function update(RoleRequest $request, Role $role, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /*
         * YÖNETİCİ BAYRAĞINI VE YÖNETİCİ ROLÜNÜ YALNIZCA YÖNETİCİ DEĞİŞTİRİR.
         *
         * "Rolleri düzenle" yetkisi olan biri kendi rolüne is_admin yazarsa
         * her kapıdan geçen bir yönetici olurdu; yönetici rolünü varsayılan
         * yapmak da yeni kayıtları yönetici açardı. Bu yüzden hem bayrağın
         * değişmesi hem de zaten yönetici olan bir rolün düzenlenmesi
         * yönetici ister. Yönetici olmayan rollerin şablonları serbest.
         */
        if (! $kullanici->yonetici()) {
            abort_if($role->is_admin || $request->boolean('is_admin'), 403);
        }

        $veri = $this->veri($request);

        /*
         * Yerleşik rolün slug'ı DEĞİŞMEZ: kod ona adıyla bakıyor. Etiketi ve
         * şablonu değişebilir; asıl düzenlenmek istenen zaten o.
         */
        if ($role->is_system) {
            unset($veri['slug']);
        }

        /*
         * SON YÖNETİCİ ROLÜ DÜŞÜRÜLEMEZ.
         *
         * Düşürülseydi paneli yönetebilecek kimse kalmazdı ve geri almanın
         * yolu da yönetim ekranından geçiyor olurdu.
         */
        if ($role->is_admin && ! ($veri['is_admin'] ?? false) && Role::where('is_admin', true)->count() === 1) {
            return back()->with('error', 'Son yönetici rolü bu yetkiden çıkarılamaz; önce başka bir yönetici rolü tanımlayın.');
        }

        $role->update($veri);

        $this->varsayilaniTekleştir($role);

        $recorder->record('rol-guncellendi', $role->label.' rolü güncellendi', $kullanici);

        return back()->with('status', $role->label.' rolü güncellendi.');
    }

    public function destroy(Role $role, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Yerleşik roller silinemez.');
        }

        /* Kullanıcısı olan rol silinirse o hesaplar rolsüz kalırdı. */
        if ($role->kullanicilar()->exists()) {
            return back()->with('error', 'Bu rolde kullanıcılar var; önce onlara başka bir rol verin.');
        }

        $ad = $role->label;
        $role->delete();

        $recorder->record('rol-silindi', $ad.' rolü silindi', $kullanici);

        return redirect()->route('admin.roles')->with('status', $ad.' rolü silindi.');
    }

    /** @return array<string, mixed> */
    private function veri(RoleRequest $request): array
    {
        $veri = $request->validated();

        /*
         * Tanınmayan yetenek KAYDEDİLMEZ.
         *
         * Gövdeye elle satır ekleyen biri, config'de olmayan bir yetkiyi
         * kayda sokabilirdi; ekranda adı olmayan bir kutucuk olarak kalır ve
         * bir gün o ad gerçek bir yetkiye denk gelirse sessizce açılırdı.
         */
        $veri['template'] = $this->sozluk->suz($veri['template'] ?? []);

        /*
         * YÖNETİM YETKİSİ KAPALIYKEN ŞABLONA YAZILMAZ.
         *
         * Ekran o satırları basmıyor ama forma elle eklenebilir. Yazılan
         * yetki bugün hiçbir şey açmazdı (PermissionResolver onu geçmeden
         * reddediyor) — yine de kayda girmesin: açık görünüp kapalı davranan
         * veri, ayar bir gün değiştiğinde sessizce yetkiye dönüşürdü.
         *
         * Kutucuk ve salon ERİŞİMLERİ elenmez; onlar yönetim değil.
         */
        if (! PermissionResolver::yetkiDagitilabilirMi()) {
            $veri['template'] = $this->sozluk->yonetimiEle($veri['template']);
        }

        return $veri;
    }

    /** Varsayılan rol TEK olur; yoksa yeni kullanıcının hangisini alacağı belirsizdir. */
    private function varsayilaniTekleştir(Role $role): void
    {
        if (! $role->is_default) {
            return;
        }

        Role::where('id', '!=', $role->id)->where('is_default', true)->update(['is_default' => false]);
    }
}
