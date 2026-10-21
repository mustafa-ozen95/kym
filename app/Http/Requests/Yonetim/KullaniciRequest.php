<?php

declare(strict_types=1);

namespace App\Http\Requests\Yonetim;

use App\Enums\Durum;
use App\Services\ParolaKurali;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yöneticinin açtığı ya da düzenlediği hesap.
 *
 * Yetkilendirme controller'ın ara katmanında; burada yalnızca biçim sınanır.
 *
 * PAROLA YENİDEN İSTENMEZ: düzenlemede boş bırakmak "değiştirme" demektir.
 * Zorunlu olsaydı, yalnızca adını düzeltmek isteyen yönetici her seferinde
 * kullanıcının parolasını sıfırlamak zorunda kalırdı.
 */
class KullaniciRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $kullanici = $this->route('user');

        /*
         * Alanlar YETKİYE göre gelir: bilgileri düzenleme yetkisi olmayana
         * ad/e-posta kutuları hiç çizilmiyor. `sometimes`, gelmeyen alanı
         * doğrulamadan geçirir; yeni hesapta ise ikisi de zorunlu.
         */
        $zorunlu = $kullanici ? 'sometimes' : 'required';

        return [
            'name' => [$zorunlu, 'string', 'max:120'],
            'email' => [
                $zorunlu, 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($kullanici?->id),
            ],

            /*
              * Kayıt ekranıyla AYNI kural: burada yalnızca uzunluk isteniyordu
              * ve "12345678" yönetici eliyle yazılabiliyordu.
              */
            'password' => [
                $kullanici ? 'nullable' : 'required',
                'confirmed',
                ParolaKurali::kural(),
            ],

            'durum' => ['sometimes', Rule::enum(Durum::class)],
            'rol' => ['nullable', 'string', Rule::exists('roller', 'uuid')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'ad', 'email' => 'e-posta', 'password' => 'parola',
            'durum' => 'durum', 'rol' => 'rol',
        ];
    }
}
