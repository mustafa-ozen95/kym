<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\TwoStepRequired;
use App\Http\Requests\Admin\MailSettingRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\EmailVerification;
use App\Services\Mail\MailSettings;
use App\Services\Permission\SettingSections;
use App\Services\RegistrationOpen;
use App\Services\TimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel ayarları.
 *
 * Ayarların ekrandan değiştirilebilmesi bilinçli: .env düzenlemek sunucuya
 * erişim ister ve değişikliği kimin yaptığı belli olmaz. Buradaki her
 * değişiklik denetim kaydına yazılır.
 */
class SettingController extends Controller implements HasMiddleware
{
    public function __construct(
        private MailSettings $posta,
        private EmailVerification $dogrulama,
        private SettingSections $bolumler,
        private RegistrationOpen $kayit,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:ayar.gor'], only: ['index']),
            new Middleware(['auth', 'can:ayar.duzenle'], only: ['update', 'posta']),

            /* Deneme iletisi DIŞARIYA çıkar: ayrı yetki (bkz. config/permissions.php). */
            new Middleware(['auth', 'can:ayar.posta-dene'], only: ['postaSina']),
        ];
    }

    public function index(#[CurrentUser] $kullanici): View
    {
        $posta = $this->posta->goster();
        $zorunlu = $this->dogrulama->zorunluMu();

        return view('admin.settings', [
            'serit' => $this->bolumler->serit(),
            'yoneticiZorunlu' => Setting::bool(TwoStepRequired::YONETICI),
            'kullaniciZorunlu' => Setting::bool(TwoStepRequired::KULLANICI),
            'hazirKullanici' => User::whereNotNull('totp_confirmed_at')->count(),
            'toplamKullanici' => User::count(),

            /*
             * Zorunluluğu açan kişi kendini dışarıda bırakabilir; kaç
             * yöneticinin hazır olduğunu görmeden karar vermemeli.
             */
            /*
             * "Yönetici" bir sütun değil, ROLÜN ÖZELLİĞİ: birden fazla
             * yönetici rolü tanımlanabilir. Sayım rol tablosundan geçer.
             */
            'hazirYonetici' => User::yoneticiler()->whereNotNull('totp_confirmed_at')->count(),
            'toplamYonetici' => User::yoneticiler()->count(),

            'posta' => $posta,
            'postaHazir' => $this->posta->hazirMi(),
            'postaSinandi' => $posta['sinandi'] ? Carbon::parse($posta['sinandi'])->format('d.m.Y H:i') : null,
            'denemeAdresi' => $kullanici->email,
            'epostaZorunlu' => $zorunlu,

            /*
             * Kilit yalnızca AÇARKEN geçerli: zorunluluk zaten açıkken kutu
             * kilitlenirse yönetici onu kapatamaz ve ayar tek yönlü olurdu.
             */
            'epostaKilitli' => ! $zorunlu && ! $this->dogrulama->acilabilirMi(),
            'epostaAcilabilir' => $this->dogrulama->acilabilirMi(),
            'kayitAcik' => $this->kayit->acikMi(),

            /* Saat dilimi: denetim kaydı takibi buna bağlı (bkz. TimeZone). */
            'saatDilimi' => TimeZone::secili(),
            'saatDilimleri' => TimeZone::liste(),

            /* Zorunluluk açıldığında kilitlenecek hesap sayısı: karar öncesi görünmeli. */
            'dogrulanmamis' => User::whereNull('email_verified_at')->count(),
        ]);
    }

    /**
     * SMTP ayarları.
     *
     * Parola ekrana HİÇ basılmaz; boş bırakıldığında eskisi korunur (bkz.
     * MailSettings::kaydet).
     */
    public function posta(MailSettingRequest $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $this->posta->kaydet($request->validated());

        $recorder->record(
            'ayar-degisti',
            'Posta ayarları güncellendi: '.$request->string('sunucu').':'.$request->string('port'),
            $kullanici,
        );

        return back()->with('status', 'Posta ayarları kaydedildi. Zorunluluğu açmadan önce deneme iletisi gönderin.');
    }

    /**
     * Deneme iletisi.
     *
     * DIŞARIYA ÇIKAN bir işlem: gerçekten posta gönderir. Bu yüzden yalnızca
     * `can:yonet` yetkisiyle çağrılabilir, sonucu denetim kaydına yazılır ve
     * alıcı isteği yapan kişinin KENDİ adresidir — bu uç, başkasına posta
     * göndermenin yolu olmamalı.
     */
    public function postaSina(ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        [$oldu, $mesaj] = $this->posta->sina($kullanici->email);

        $recorder->record('posta-deneme', $oldu ? 'Deneme iletisi gönderildi' : 'Deneme iletisi başarısız: '.$mesaj, $kullanici);

        return back()->with($oldu ? 'status' : 'error', $mesaj);
    }

    public function update(Request $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $yonetici = $request->boolean('iki_adim_zorunlu');
        $herkes = $request->boolean('iki_adim_zorunlu_kullanici');

        /*
         * Kendini kilitlemeyi ENGELLE.
         *
         * Zorunluluğu açan yöneticinin kendi ikinci adımı kurulu değilse,
         * kaydettiği anda kendi kapısını kapatır ve geri açacak kimse
         * kalmayabilir.
         */
        if (($yonetici || $herkes) && ! $kullanici->ikiAdimliMi()) {
            return back()->with('error', 'Önce kendi ikinci adımınızı kurun; yoksa bu ayarı açar açmaz siz de giremezsiniz.');
        }

        $eposta = $request->boolean('eposta_dogrulama_zorunlu');

        /*
         * POSTA GİTMİYORKEN ZORUNLULUK AÇILMAZ.
         *
         * Açılsaydı doğrulama bağlantısı kimseye ulaşmaz ve yeni kayıtların
         * tamamı sessizce kilitlenirdi — üstelik bunu fark edecek kimse
         * olmazdı, çünkü kilitlenen kişi zaten içeri giremiyor. Bu yüzden
         * ölçüt "ayar dolu mu" değil, "deneme iletisi GERÇEKTEN gitti mi".
         */
        if ($eposta && ! $this->dogrulama->acilabilirMi()) {
            return back()->with('error', 'Önce posta ayarlarını kaydedip deneme iletisi gönderin; postanın gittiği kanıtlanmadan doğrulama zorunlu tutulamaz.');
        }

        Setting::yaz(TwoStepRequired::YONETICI, $yonetici);
        Setting::yaz(TwoStepRequired::KULLANICI, $herkes);
        Setting::yaz(EmailVerification::AYAR, $eposta);
        Setting::yaz(RegistrationOpen::AYAR, $request->boolean('kayit_acik'));

        /*
         * Saat dilimi listeden GEÇERLİ olmalı: serbest metin kabul edilseydi
         * yazım hatası bütün tarihleri sessizce UTC'ye düşürürdü.
         */
        $dilim = (string) $request->input('saat_dilimi', '');
        $dilimDegisti = false;

        if ($dilim !== '' && TimeZone::gecerliMi($dilim) && $dilim !== TimeZone::secili()) {
            Setting::yaz(TimeZone::AYAR, $dilim);
            TimeZone::uygula();
            $dilimDegisti = true;
        }

        $recorder->record(
            'ayar-degisti',
            'İkinci adım zorunluluğu — yöneticiler: '.($yonetici ? 'açık' : 'kapalı')
                .', tüm kullanıcılar: '.($herkes ? 'açık' : 'kapalı')
                .'; e-posta doğrulama: '.($eposta ? 'zorunlu' : 'isteğe bağlı')
                .'; kayıt: '.($request->boolean('kayit_acik') ? 'açık' : 'kapalı')
                .($dilimDegisti ? '; saat dilimi: '.$dilim : ''),
            $kullanici,
        );

        return back()->with('status', 'Ayarlar kaydedildi.');
    }
}
