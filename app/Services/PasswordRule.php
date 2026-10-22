<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * Parolanın ne sayıldığı: TEK YER.
 *
 * Parola belirlemenin dört yolu var (kayıt, profil, yöneticinin kullanıcı
 * ekleme ekranı, konsol komutu) ve kural dördünde ayrı ayrı yazılıydı.
 * Dördü aynı değildi: kayıt harf ve rakam isterken yönetici ekranı yalnızca
 * uzunluğa bakıyordu — yani "12345678" kayıt ekranından geçmezken yönetici
 * eliyle aynı hesaba yazılabiliyordu. Kural kopyalanmaz, paylaştırılır.
 *
 * Aynı sınıf ölçütleri okunabilir biçimde de verir: kayıt ekranı bunları
 * CANLI gösterir, böylece kullanıcı "yeterli mi" sorusunu formu gönderip
 * sunucudan hata almadan görür. Ekran kuralı kendi bilmez, buradan sorar.
 */
class PasswordRule
{
    public static function uzunluk(): int
    {
        return (int) config('limits.password_length');
    }

    /** Laravel doğrulama kuralı — otorite budur. */
    public static function kural(): Password
    {
        return Password::min(self::uzunluk())->letters()->numbers();
    }

    /**
     * Ekranda canlı gösterilen ölçütler.
     *
     * Anahtarlar JS tarafındaki denetimlerin adıdır; metin kullanıcıya
     * gider. Sıra, bir parolayı yazarken karşılaşılma sırasıdır.
     *
     * @return array<int, array{anahtar: string, metin: string}>
     */
    public static function olcutler(): array
    {
        return [
            ['anahtar' => 'uzunluk', 'metin' => 'En az '.self::uzunluk().' karakter'],
            ['anahtar' => 'harf', 'metin' => 'En az bir harf'],
            ['anahtar' => 'rakam', 'metin' => 'En az bir rakam'],
            /* Eksik listesinde çıktığı için YAPILACAK İŞİ söyler, durumu değil. */
            ['anahtar' => 'eslesme', 'metin' => 'İki parola aynı olmalı'],
        ];
    }
}
