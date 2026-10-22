<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityRecorder;
use App\Services\Permission\SettingSections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Denetim kaydı — kim ne yaptı, kim nereye girdi.
 *
 * ADI "Denetim", "Kayıt" DEĞİL: Türkçede "kayıt" hem üyelik kaydı hem denetim
 * satırı demek ve `Auth\RegistrationController` zaten üyeliği yönetiyor. İki sınıf aynı
 * adı taşıyınca rota dosyası derlenmiyordu.
 *
 * KAYIT DEĞİŞTİRİLEMEZ. Her satırın özeti bir öncekinin özetini de kapsıyor;
 * bir satırı sonradan düzenlemek tek satırı değiştirmekle bitmez, zincirin
 * tamamı bozulur ve doğrulama bunu görür. Anahtar uygulamanın kendi anahtarı,
 * yani veritabanına erişen biri zinciri yeniden üretemez.
 *
 * EKRANDA SİLME YOK ve olmayacak: silinebilen bir denetim kaydı, denetim
 * kaydı değildir.
 */
class AuditController extends Controller implements HasMiddleware
{
    private const PAGE_SIZE = 50;

    public function __construct(private SettingSections $bolumler) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:kayit.gor'], only: ['index']),
            new Middleware(['auth', 'can:kayit.dogrula'], only: ['verify']),
        ];
    }

    public function index(Request $request, #[CurrentUser] $kullanici): View
    {
        $eylem = (string) $request->query('action', '');
        $arama = trim((string) $request->query('search', ''));

        $sorgu = ActivityLog::with('user')->orderByDesc('id');

        if ($eylem !== '') {
            $sorgu->where('eylem', $eylem);
        }

        /*
         * Arama ÖZET ve ADDA. IP'de aranmıyor: kimin hangi adresten geldiğini
         * sorgulamak ayrı bir iş ve bu ekranın işi değil.
         */
        if ($arama !== '') {
            $sorgu->where(function ($q) use ($arama): void {
                $q->where('ozet', 'like', '%'.$arama.'%')
                    ->orWhere('actor_name', 'like', '%'.$arama.'%');
            });
        }

        return view('admin.logs', [
            'serit' => $this->bolumler->serit(),
            'kayitlar' => $sorgu->paginate(self::PAGE_SIZE)->withQueryString(),

            /* Süzme listesi KAYITTAN gelir: elle tutulan bir liste eskir. */
            'eylemler' => ActivityLog::query()->distinct()->orderBy('eylem')->pluck('eylem')->all(),
            'secilenEylem' => $eylem,
            'arama' => $arama,
            'dogrulayabilir' => $kullanici->can('kayit.dogrula'),
        ]);
    }

    /**
     * Zinciri baştan sona sınar.
     *
     * Sonuç kaydın KENDİSİNE de yazılır: doğrulamanın ne zaman ve kim
     * tarafından yapıldığı da denetlenebilir bir bilgidir.
     */
    public function verify(ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $sonuc = $recorder->verify();

        /*
         * broken_id BOŞSA satırlar kendi içinde tutarlı ama zincirin ucu ya
         * da uzunluğu çapayla uyuşmuyor: son kayıtlar silinmiş demektir.
         */
        $kirik = $sonuc['broken_id'] === null
            ? 'ZİNCİR KIRIK: kayıtlar çapayla uyuşmuyor ('.$sonuc['checked'].' satır kaldı); son kayıtlar silinmiş olabilir.'
            : 'ZİNCİR KIRIK: '.$sonuc['checked'].'. satırda (kayıt #'.$sonuc['broken_id'].'). Kayıt sonradan değiştirilmiş olabilir.';

        $recorder->record(
            'kayit-dogrulandi',
            $sonuc['ok'] ? $this->butun($sonuc) : $kirik,
            $kullanici,
        );

        return back()->with(
            $sonuc['ok'] ? 'status' : 'error',
            $sonuc['ok'] ? $this->butun($sonuc) : $kirik,
        );
    }

    /** @param  array{checked: int, muhurlu: int}  $sonuc */
    private function butun(array $sonuc): string
    {
        return $sonuc['checked'].' satır doğrulandı; zincir bütün.'
            .($sonuc['muhurlu'] > 0 ? ' '.$sonuc['muhurlu'].' satır bilinen eski kırık olarak mühürlü ve değişmemiş.' : '');
    }
}
