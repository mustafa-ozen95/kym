<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Mail\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Posta ayarı isteği.
 *
 * Yetkilendirme controller'ın `can:yonet` ara katmanında; burada yalnızca
 * biçim sınanır.
 */
class MailSettingRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'server' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(MailSettings::ENCRYPTION_OPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],

            /* Boş = değiştirme. Ekranda hiç görünmediği için "sil" anlamına gelemez. */
            'password' => ['nullable', 'string', 'max:255'],

            'sender' => ['required', 'email', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'server' => 'sunucu adresi',
            'port' => 'port',
            'encryption' => 'şifreleme',
            'username' => 'kullanıcı adı',
            'password' => 'password',
            'sender' => 'gönderen adresi',
            'sender_name' => 'gönderen adı',
        ];
    }
}
