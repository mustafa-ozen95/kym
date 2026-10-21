<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Group;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Görsel testlerin veri kaynağı.
 *
 * Görsel takım ÜRETİM veritabanına bakmamalı: hem gerçek kayıtları değiştirme
 * riski var hem de ekranlar boşsa (ya da bir gün doluysa) testler veriye göre
 * salınır. Burada sabit, küçük ve yeterli bir dünya kurulur — bir yönetici,
 * iki salon, dört kutucuk.
 *
 * Yalnızca yerel sqlite üzerinde çalıştırılır (bkz. README, "Görsel testler").
 */
class VisualSeeder extends Seeder
{
    public const EPOSTA = 'gorsel@ornek.test';

    public const PAROLA = 'gorsel-test-1616';

    public function run(): void
    {
        $rol = Role::query()->updateOrCreate(
            ['slug' => 'yonetici'],
            ['label' => 'Yönetici', 'is_admin' => true, 'position' => 1],
        );

        User::query()->updateOrCreate(
            ['email' => self::EPOSTA],
            [
                'name' => 'Görsel Test',
                'password' => Hash::make(self::PAROLA),
                'rol_id' => $rol->getKey(),
                'durum' => Status::Etkin,
                'email_verified_at' => now(),
            ],
        );

        $salonlar = [
            ['anahtar' => 'salon-a', 'ad' => 'Salon A', 'sira' => 1],
            ['anahtar' => 'ofis', 'ad' => 'Ofis', 'sira' => 2],
        ];

        foreach ($salonlar as $salon) {
            Group::query()->updateOrCreate(['anahtar' => $salon['anahtar']], $salon);
        }

        $kutucuklar = [
            ['anahtar' => 'eys', 'ad' => 'Erişim Yönetimi', 'adres' => 'https://panel-a.ornek.test', 'grup' => 'salon-a', 'sira' => 1],
            ['anahtar' => 'nys', 'ad' => 'Network Yönetimi', 'adres' => 'https://panel-b.ornek.test', 'grup' => 'salon-a', 'sira' => 2],
            ['anahtar' => 'wiki', 'ad' => 'Belgeler', 'adres' => 'https://ornek.test/wiki', 'grup' => 'ofis', 'sira' => 1],
            ['anahtar' => 'takvim', 'ad' => 'Takvim', 'adres' => 'https://ornek.test/takvim', 'grup' => 'ofis', 'sira' => 2],
        ];

        foreach ($kutucuklar as $kutucuk) {
            System::query()->updateOrCreate(
                ['anahtar' => $kutucuk['anahtar']],
                $kutucuk + ['etkin' => true],
            );
        }
    }
}
