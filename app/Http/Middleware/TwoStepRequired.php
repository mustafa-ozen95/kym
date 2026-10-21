<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * İkinci adım zorunluluğu — AYARA BAĞLI, iki ayrı anahtar.
 *
 *   yoneticiler : yönetim ekranlarını ikinci adımsız açtırmaz
 *   kullanicilar: herkesi, hiçbir şey yapmadan önce kuruluma gönderir
 *
 * İkisi de varsayılan olarak KAPALI. Ayrı olmaları
 * bilinçli: yönetici hesabı bütün sistemlerin anahtarını dağıtıyor, sıradan
 * kullanıcı yalnızca kendi erişimini taşıyor — aynı eşiği ikisine birden
 * dayatmak zorunda değiliz.
 *
 * Kapı kapatılmaz, yol gösterilir: kullanıcı kurulum sayfasına yönlendirilir.
 */
class TwoStepRequired
{
    public const YONETICI = 'iki_adim_zorunlu';

    public const KULLANICI = 'iki_adim_zorunlu_kullanici';

    /** Zorunluluk bu yolları kapatmaz; yoksa kullanıcı kurulumu yapamaz. */
    private const MUAF = ['hesap/iki-adim', 'hesap/iki-adim/*', 'cikis'];

    public function handle(Request $request, Closure $next): Response
    {
        $kullanici = $request->user();

        if (! $kullanici || $kullanici->ikiAdimliMi() || $request->is(...self::MUAF)) {
            return $next($request);
        }

        if (! $this->zorunluMu($request, $kullanici)) {
            return $next($request);
        }

        return redirect()->route('hesap.iki-adim')
            ->with('status', 'İkinci adım zorunlu tutuluyor. Devam etmek için lütfen kurun.');
    }

    private function zorunluMu(Request $request, $kullanici): bool
    {
        if (Setting::bool(self::KULLANICI)) {
            return true;
        }

        /*
         * Yönetici kuralı yönetim ekranlarını kapsar ve "yönetici"yi ROL
         * BAYRAĞINA göre değil, o ekranlara GİREBİLMEYE göre tanır.
         *
         * Yetki dağıtımı açıkken (yonetim_yalnizca_yonetici=false) yönetici
         * olmayan ama yönetim yetkisi verilmiş biri de can:yonet kapısından
         * geçer; kural yalnızca is_admin'e bakarsa o kişi tek adımla
         * yönetim ekranlarında kalırdı. Kapıyla aynı soru sorulur.
         */
        return Setting::bool(self::YONETICI)
            && $request->is('yonetim*')
            && ($kullanici->yonetici() || Gate::forUser($kullanici)->allows('yonet'));
    }
}
