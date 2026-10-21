<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Models\OauthIstemci;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bağlı uygulamalara imzalı bildirim — kimlik değişikliği ve ortak çıkış
 * aynı yoldan gider; adres ve imza kuralı TEK yerde.
 *
 * Adres istemcinin kayıtlı dönüş adreslerinin kökünden türetilir. İmza
 * istemciye özgü bildirim sırrıyla HMAC-SHA256; sırrı olmayan istemci
 * bildirim almaz.
 */
trait IstemcilereBildirir
{
    /** Yanıt beklemesi (saniye). Proje kapalıysa kuyruk burada tıkanmamalı. */
    private const ZAMAN_ASIMI = 5;

    /**
     * @param  callable(OauthIstemci): bool|null  $suzgec
     * @param  array<string, mixed>  $govde
     */
    private function istemcilereBildir(string $yol, array $govde, string $imzalanan, ?callable $suzgec = null): void
    {
        foreach (OauthIstemci::where('revoked', false)->get() as $istemci) {
            if ($suzgec !== null && ! $suzgec($istemci)) {
                continue;
            }

            $sir = (string) ($istemci->getAttributes()['bildirim_sirri'] ?? '');

            if ($sir === '') {
                continue;
            }

            foreach ($this->adresler($istemci, $yol) as $adres) {
                $this->gonder($adres, $govde, hash_hmac('sha256', $imzalanan, $sir), $istemci->name);
            }
        }
    }

    /**
     * Projenin bildirim adresi, KAYITLI DÖNÜŞ ADRESİNDEN türetilir.
     *
     * Ayrı bir alan istemek, kurulumda doldurulacak ve zamanla eskiyecek
     * ikinci bir adres olurdu; dönüş adresi zaten projenin kökünü söylüyor.
     *
     * @return list<string>
     */
    private function adresler(OauthIstemci $istemci, string $yol): array
    {
        $adresler = [];

        foreach ((array) ($istemci->redirect_uris ?? []) as $uri) {
            $parca = parse_url((string) $uri);

            if (! isset($parca['scheme'], $parca['host'])) {
                continue;
            }

            $kok = $parca['scheme'].'://'.$parca['host'].(isset($parca['port']) ? ':'.$parca['port'] : '');
            $adresler[$kok] = $kok.$yol;
        }

        return array_values($adresler);
    }

    /** @param  array<string, mixed>  $govde */
    private function gonder(string $adres, array $govde, string $imza, string $istemciAdi): void
    {
        try {
            $yanit = Http::timeout(self::ZAMAN_ASIMI)
                ->withHeaders(['X-Kimlik-Imza' => $imza])
                ->acceptJson()
                ->post($adres, $govde);

            if ($yanit->successful()) {
                return;
            }

            Log::warning('Kimlik bildirimi reddedildi', [
                'istemci' => $istemciAdi, 'adres' => $adres, 'kod' => $yanit->status(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Kimlik bildirimi gönderilemedi', [
                'istemci' => $istemciAdi, 'adres' => $adres, 'hata' => $e->getMessage(),
            ]);
        }
    }
}
