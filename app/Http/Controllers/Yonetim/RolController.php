<?php

declare(strict_types=1);

namespace App\Http\Controllers\Yonetim;

use App\Enums\BadgeVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Yonetim\RolRequest;
use App\Models\Rol;
use App\Services\ActivityRecorder;
use App\Services\Yetki\AyarBolumleri;
use App\Services\Yetki\YetkiCozumleyici;
use App\Services\Yetki\YetkiSozlugu;
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
class RolController extends Controller implements HasMiddleware
{
    public function __construct(
        private YetkiSozlugu $sozluk,
        private AyarBolumleri $bolumler,
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
        return view('yonetim.roller', [
            'serit' => $this->bolumler->serit(),

            /* N+1: her satır kaç kullanıcısı olduğunu yazıyor. */
            'roller' => Rol::withCount('kullanicilar')->sirali()->get(),
            'rozetler' => $this->rozetler(),
            'duzenleyebilir' => $kullanici->can('rol.duzenle'),
        ]);
    }

    public function show(Rol $rol, #[CurrentUser] $kullanici): View
    {
        return view('yonetim.rol', [
            'serit' => $this->bolumler->serit(),
            'rol' => $rol,
            'kaynaklar' => $this->sozluk->ekranSatirlari(YetkiCozumleyici::yetkiDagitilabilirMi()),
            'sablon' => $rol->yetenekler(),
            'rozetler' => $this->rozetler(),
            'kullaniciSayisi' => $rol->kullanicilar()->count(),
            'duzenleyebilir' => $kullanici->can('rol.duzenle'),

            /*
             * Yetki dağıtımı açık mı? Kapalıyken ekran kutucuk göstermez;
             * işaretlenip hiçbir şey açmayan bir kutu, işaretleyene yalan
             * söyler (bkz. YetkiCozumleyici).
             */
            'yetkiDagitilabilir' => YetkiCozumleyici::yetkiDagitilabilirMi(),
        ]);
    }

    /** @return array<string, string> */
    private function rozetler(): array
    {
        return collect(BadgeVariant::cases())
            ->mapWithKeys(fn (BadgeVariant $rozet): array => [$rozet->value => $rozet->value])
            ->all();
    }

    public function store(RolRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        /* Yönetici bayrağı yalnızca yöneticide: bkz. update(). */
        abort_if($request->boolean('is_admin') && ! $kullanici->yonetici(), 403);

        $rol = Rol::create($this->veri($request));

        $this->varsayilaniTekleştir($rol);

        $recorder->record('rol-eklendi', $rol->label.' rolü açıldı', $kullanici);

        return redirect()->route('yonetim.roller.goster', $rol)
            ->with('status', $rol->label.' rolü açıldı; yetkilerini şimdi seçebilirsiniz.');
    }

    public function update(RolRequest $request, Rol $rol, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
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
            abort_if($rol->is_admin || $request->boolean('is_admin'), 403);
        }

        $veri = $this->veri($request);

        /*
         * Yerleşik rolün slug'ı DEĞİŞMEZ: kod ona adıyla bakıyor. Etiketi ve
         * şablonu değişebilir; asıl düzenlenmek istenen zaten o.
         */
        if ($rol->is_system) {
            unset($veri['slug']);
        }

        /*
         * SON YÖNETİCİ ROLÜ DÜŞÜRÜLEMEZ.
         *
         * Düşürülseydi paneli yönetebilecek kimse kalmazdı ve geri almanın
         * yolu da yönetim ekranından geçiyor olurdu.
         */
        if ($rol->is_admin && ! ($veri['is_admin'] ?? false) && Rol::where('is_admin', true)->count() === 1) {
            return back()->with('error', 'Son yönetici rolü bu yetkiden çıkarılamaz; önce başka bir yönetici rolü tanımlayın.');
        }

        $rol->update($veri);

        $this->varsayilaniTekleştir($rol);

        $recorder->record('rol-guncellendi', $rol->label.' rolü güncellendi', $kullanici);

        return back()->with('status', $rol->label.' rolü güncellendi.');
    }

    public function destroy(Rol $rol, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        if ($rol->is_system) {
            return back()->with('error', 'Yerleşik roller silinemez.');
        }

        /* Kullanıcısı olan rol silinirse o hesaplar rolsüz kalırdı. */
        if ($rol->kullanicilar()->exists()) {
            return back()->with('error', 'Bu rolde kullanıcılar var; önce onlara başka bir rol verin.');
        }

        $ad = $rol->label;
        $rol->delete();

        $recorder->record('rol-silindi', $ad.' rolü silindi', $kullanici);

        return redirect()->route('yonetim.roller')->with('status', $ad.' rolü silindi.');
    }

    /** @return array<string, mixed> */
    private function veri(RolRequest $request): array
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
         * yetki bugün hiçbir şey açmazdı (YetkiCozumleyici onu geçmeden
         * reddediyor) — yine de kayda girmesin: açık görünüp kapalı davranan
         * veri, ayar bir gün değiştiğinde sessizce yetkiye dönüşürdü.
         *
         * Kutucuk ve salon ERİŞİMLERİ elenmez; onlar yönetim değil.
         */
        if (! YetkiCozumleyici::yetkiDagitilabilirMi()) {
            $veri['template'] = $this->sozluk->yonetimiEle($veri['template']);
        }

        return $veri;
    }

    /** Varsayılan rol TEK olur; yoksa yeni kullanıcının hangisini alacağı belirsizdir. */
    private function varsayilaniTekleştir(Rol $rol): void
    {
        if (! $rol->is_default) {
            return;
        }

        Rol::where('id', '!=', $rol->id)->where('is_default', true)->update(['is_default' => false]);
    }
}
