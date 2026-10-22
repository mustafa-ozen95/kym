<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Merkezin ilk yöneticisi. Parola ORTAMDAN gelir; koda gömülmez.
 *
 * Yalnızca kurulumda çağrılır (docker/rootfs/usr/local/bin/identity-entrypoint,
 * kullanıcı tablosu boşken). Rol adıyla değil `is_admin` ile bulunur: yerleşik
 * rollerin etiketi değişebilir, "bütün kontrolleri geçen rol" olma niteliği
 * değişmez.
 *
 * Hesap DOĞRULANMIŞ ve ETKİN doğar: kurulumu yapan kişinin kendi açtığı
 * merkezde e-posta doğrulamasını bekleyecek bir posta sunucusu henüz yok.
 */
class FirstAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Bilgi verilmediyse hesap AÇILMAZ. Varsayılan bir adres ve parolayla
        // hesap açmak, imajı indiren herkeste aynı girişin bulunması demek
        // olurdu. Yönetici `admin:add` ile eklenir.
        if (blank(config('app.admin_email')) || blank(config('app.admin_password'))) {
            return;
        }

        $rol = Role::query()->where('is_admin', true)->value('id');

        if ($rol === null) {
            $this->command?->error('Yönetici rolü yok; göçler koşmadan bu seeder çalıştırılamaz.');

            return;
        }

        User::updateOrCreate(
            ['email' => config('app.admin_email')],
            [
                'name' => config('app.admin_name'),
                'password' => Hash::make(config('app.admin_password')),
                'rol_id' => $rol,
                'durum' => Status::Etkin,
                'email_verified_at' => now(),
            ],
        );
    }
}
