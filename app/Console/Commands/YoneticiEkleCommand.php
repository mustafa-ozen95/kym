<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Durum;
use App\Models\Rol;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\ParolaKurali;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * İlk yöneticiyi açar.
 *
 * Panelde "kendini yönetici yap" diye bir yol YOKTUR ve olmayacaktır: kayıt
 * ekranından açılan her hesap beklemede doğar. Zinciri başlatan bu komut,
 * yalnızca sunucuya erişebilen kişinin elindedir.
 *
 * Var olan bir hesabı yöneticiye çevirmek için de aynı komut kullanılır;
 * parola verilmezse dokunulmaz.
 */
class YoneticiEkleCommand extends Command
{
    protected $signature = 'kimlik:yonetici-ekle
                            {--ad= : Görünen ad}
                            {--eposta= : E-posta}
                            {--parola= : Parola (sorulmazsa istenir)}';

    protected $description = 'Merkeze yönetici yetkili, etkin bir kullanıcı ekler';

    public function handle(ActivityRecorder $recorder): int
    {
        $ad = $this->option('ad') ?: $this->ask('Ad');
        $eposta = $this->option('eposta') ?: $this->ask('E-posta');
        $mevcut = User::where('email', $eposta)->first();

        $parola = $this->option('parola')
            ?: ($mevcut ? null : $this->secret('Parola'));

        $dogrulama = Validator::make(
            ['ad' => $ad, 'eposta' => $eposta, 'parola' => $parola],
            [
                'ad' => ['required', 'string', 'max:120'],
                'eposta' => ['required', 'email', 'max:255'],
                'parola' => [$mevcut ? 'nullable' : 'required', ParolaKurali::kural()],
            ],
        );

        if ($dogrulama->fails()) {
            foreach ($dogrulama->errors()->all() as $hata) {
                $this->error($hata);
            }

            return self::FAILURE;
        }

        $kullanici = User::updateOrCreate(
            ['email' => $eposta],
            [
                'name' => $ad,
                'rol_id' => Rol::firstWhere('is_admin', true)?->id,
                /* Konsoldan açılan hesabı da bir insan açıyor: adres doğrulanmış sayılır. */
                'email_verified_at' => now(),
                ...($parola ? ['password' => $parola] : []),
            ],
        );

        /* Durum toplu atamaya kapalı: konsoldan açılan hesabı da bir insan açıyor. */
        $kullanici->durumuAyarla(Durum::Etkin);

        /* Yetki verilmesi de kayda geçer: "bu hesap nereden yönetici oldu". */
        $recorder->record('yonetici-atandi', $kullanici->email.' yönetici yapıldı (konsol)', $kullanici);

        $this->info(($mevcut ? 'Güncellendi' : 'Eklendi').': '.$kullanici->email.' — '.$kullanici->rol?->label);

        return self::SUCCESS;
    }
}
