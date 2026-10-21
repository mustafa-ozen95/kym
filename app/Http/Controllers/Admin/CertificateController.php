<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Certificate\CertificateType;
use App\Services\Permission\SettingSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * TLS sertifikası — durum, doğrulama kayıtları ve tur başlatma.
 *
 * NEDEN ELLE: panel adresleri özel ağdaysa Let's Encrypt HTTP ile
 * doğrulayamaz. DNS-01 işler; DNS sağlayıcısında otomasyon yoksa kaydı bir
 * insan ekler. Bu ekran o insanın işini kolaylaştırır: kaydı ekranda
 * gösterir, yayıldığını kendisi doğrular, sonra turu tamamlar.
 *
 * PANEL SERTİFİKA ALMAZ. ACME hesabı, özel anahtar ve sertifika dosyaları
 * sunucuda durur; panel yalnızca küçük bir istek dosyası bırakır ve sonucunu
 * okur (sunucudaki sertifika ajanı). Sertifika alabilen bir web uygulaması,
 * ele geçirildiğinde kurumun alan adları için geçerli sertifika
 * üretebilirdi — o yetki sunucuda kalıyor.
 */
class CertificateController extends Controller implements HasMiddleware
{
    public function __construct(
        private SettingSections $bolumler,
        private CertificateType $sertifika,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'can:ayar.gor'], only: ['index']),
            new Middleware(['auth', 'can:ayar.sertifika'], only: ['baslat', 'tamamla']),
        ];
    }

    public function index(): View
    {
        return view('admin.certificate', [
            'serit' => $this->bolumler->serit(),
            'durum' => $this->sertifika->durum(),
            'kayitlar' => $this->sertifika->kayitlar(),
            'islem' => $this->sertifika->sonIslem(),
            'yayilma' => $this->sertifika->yayilmaDurumu(),
            'bekliyor' => $this->sertifika->istekBekliyorMu(),
        ]);
    }

    /**
     * Yeni tur: sunucu ACME siparişini açar ve TXT kayıtlarını yazar.
     */
    public function baslat(): RedirectResponse
    {
        if (! $this->sertifika->istekBirak('baslat')) {
            return $this->iletilemedi();
        }

        return back()->with('status', 'Tur başlatıldı. Kayıtlar birkaç saniye içinde burada görünecek.');
    }

    /**
     * Onayla: önce kayıtların YAYILDIĞINI panel kendi sorar, sonra turu
     * tamamlatır.
     *
     * Doğrulamayı atlayıp doğrudan tamamlatmak, yayılmamış bir kayıtla
     * sertifika sağlayıcısına gitmek olurdu; o deneme başarısız sayılır ve
     * tur baştan başlar.
     */
    public function tamamla(): RedirectResponse
    {
        $eksik = collect($this->sertifika->yayilmaDurumu())
            ->reject(fn (array $k): bool => $k['yayildi'])
            ->pluck('ad')
            ->all();

        if ($eksik !== []) {
            return back()->withErrors([
                'sertifika' => 'Şu kayıtlar DNS\'te henüz görünmüyor: '.implode(', ', $eksik),
            ]);
        }

        if (! $this->sertifika->istekBirak('tamamla')) {
            return $this->iletilemedi();
        }

        return back()->with('status', 'Sertifika alınıyor. Bu işlem bir dakikayı bulabilir.');
    }

    /**
     * İstek dosyası yazılamadı: sunucudaki ajan hiçbir şey görmez. Yine de
     * "tur başlatıldı" demek kullanıcıyı boşuna bekletir; istek dizini
     * başka bir kullanıcıya aitse sekme tam olarak böyle sessizce hiçbir şey
     * yapmazdı.
     */
    private function iletilemedi(): RedirectResponse
    {
        return back()->withErrors([
            'sertifika' => 'İstek sunucuya iletilemedi: panel istek dizinine (/etc/dc/sertifika-panel) yazamıyor. Sunucu yöneticisine bildirin.',
        ]);
    }
}
