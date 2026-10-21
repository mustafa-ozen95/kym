<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityRecorder;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

/**
 * Bir projeyi merkeze istemci olarak kaydeder.
 *
 * İSTEMCİLER ELLE KAYDEDİLİR. Dinamik kayıt açık olsaydı, adresi bilen
 * herkes kendini istemci yapıp kullanıcıları kendi sayfasına yönlendirebilir
 * ve "kurum girişi" görüntüsü altında kimlik toplayabilirdi.
 *
 * Dönüş adresi TAM yazılır. Passport yalnızca birebir eşleşeni kabul eder;
 * bu, kodun başka bir yere gönderilmesini engelleyen asıl korumadır.
 *
 * Sır YALNIZCA BURADA, bir kez görünür — saklanmaz, gösterilmez.
 */
class IstemciEkleCommand extends Command
{
    protected $signature = 'kimlik:istemci-ekle
                            {--ad= : Projenin adı (onay ekranında görünür)}
                            {--adres=* : İzin verilen dönüş adresi; birden çok verilebilir}';

    protected $description = 'Bir projeyi kimlik merkezine istemci olarak kaydeder';

    public function handle(ClientRepository $istemciler, ActivityRecorder $recorder): int
    {
        $ad = $this->option('ad') ?: $this->ask('Proje adı');
        $adresler = $this->option('adres') ?: [$this->ask('Dönüş adresi (https://.../giris/merkez/geri)')];

        foreach ($adresler as $adres) {
            if (! filter_var($adres, FILTER_VALIDATE_URL) || ! str_starts_with($adres, 'https://')) {
                $this->error('Dönüş adresi https ile başlayan tam bir adres olmalı: '.$adres);

                return self::FAILURE;
            }
        }

        $istemci = $istemciler->createAuthorizationCodeGrantClient($ad, $adresler);

        $recorder->record('istemci-eklendi', $ad.' istemci olarak kaydedildi');

        $this->info('İstemci kaydedildi.');
        $this->newLine();
        $this->line('  Ad     : '.$ad);
        $this->line('  Kimlik : '.$istemci->getKey());
        $this->line('  Sır    : '.$istemci->plainSecret);
        $this->line('  Adres  : '.implode(', ', $adresler));
        $this->newLine();
        $this->warn('Sır bir daha gösterilmeyecek; şimdi projenin .env dosyasına yazın.');

        return self::SUCCESS;
    }
}
