<?php

declare(strict_types=1);

namespace App\Http\Requests\Yonetim;

use App\Services\Posta\PostaAyarlari;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Posta ayarı isteği.
 *
 * Yetkilendirme controller'ın `can:yonet` ara katmanında; burada yalnızca
 * biçim sınanır.
 */
class PostaAyariRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sunucu' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'sifreleme' => ['required', Rule::in(PostaAyarlari::SIFRELEME_SECENEKLERI)],
            'kullanici' => ['nullable', 'string', 'max:255'],

            /* Boş = değiştirme. Ekranda hiç görünmediği için "sil" anlamına gelemez. */
            'parola' => ['nullable', 'string', 'max:255'],

            'gonderen' => ['required', 'email', 'max:255'],
            'gonderen_ad' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sunucu' => 'sunucu adresi',
            'port' => 'port',
            'sifreleme' => 'şifreleme',
            'kullanici' => 'kullanıcı adı',
            'parola' => 'parola',
            'gonderen' => 'gönderen adresi',
            'gonderen_ad' => 'gönderen adı',
        ];
    }
}
