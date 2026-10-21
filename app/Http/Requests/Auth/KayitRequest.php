<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\ParolaKurali;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kayıt isteği.
 *
 * Kayıt herkese açıktır; güvenliği sağlayan şey kaydın kapalı olması değil,
 * yeni hesabın BEKLEMEDE doğmasıdır (bkz. Durum).
 */
class KayitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', ParolaKurali::kural()],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'ad', 'email' => 'e-posta', 'password' => 'parola'];
    }
}
