<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Durum;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('parola-1234'),
            'remember_token' => Str::random(10),

            /*
             * Testlerin çoğu çalışan bir hesap ister; bekleyen hâli ayrı durum.
             *
             * Rol VARSAYILAN roldür, "ilk kayıt" değil: aranan slug
             * bulunamayınca listenin başındaki role düşülseydi o rol ADMIN
             * olabilirdi — sıradan kullanıcı sanılan hesaplar sessizce her
             * şeyi yapabilir, "giremez" testleri de sessizce geçerdi.
             *
             * Varsayılan yoksa yönetici OLMAYAN ilk rol; hiç rol yoksa rolsüz.
             * Eksik veri yetki üretmemeli.
             */
            'rol_id' => fn (): ?int => Rol::varsayilan()?->id
                ?? Rol::where('is_admin', false)->value('id'),
            'durum' => Durum::Etkin,
        ];
    }

    public function beklemede(): static
    {
        return $this->state(['durum' => Durum::Beklemede]);
    }

    public function askida(): static
    {
        return $this->state(['durum' => Durum::Askida]);
    }

    public function yonetici(): static
    {
        return $this->state(['rol_id' => fn (): ?int => Rol::firstWhere('is_admin', true)?->id]);
    }

    /**
     * Belirli bir yerleşik rol: yetki senaryoları bunu kullanır.
     *
     * Rol bulunamazsa hesap ROLSÜZ kalır ve hiçbir şey yapamaz — sessizce
     * başka bir role düşmek, testin sınadığı şeyi değiştirirdi.
     */
    public function rol(string $slug): static
    {
        return $this->state(['rol_id' => fn (): ?int => Rol::firstWhere('slug', $slug)?->id]);
    }

    /**
     * İkinci adımı kurulmuş hesap.
     *
     * Yönetim ekranları ikinci adım olmadan açılmıyor (bkz.
     * YoneticiIkiAdimIster); gerçek bir yöneticiyi taklit eden testler bu
     * durumu kullanır.
     */
    public function ikiAdimli(): static
    {
        return $this->state([
            'totp_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'totp_confirmed_at' => now(),
            'totp_recovery' => [],
        ]);
    }
}
