<?php

declare(strict_types=1);

namespace App\Http\Requests\Yonetim;

use App\Enums\BadgeVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rol isteği.
 *
 * Yetkilendirme controller'ın `can:rol.duzenle` ara katmanında; burada
 * yalnızca biçim sınanır.
 */
class RolRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rol = $this->route('rol');

        return [
            'slug' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roller', 'slug')->ignore($rol?->id),
            ],
            'label' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'badge' => ['required', Rule::enum(BadgeVariant::class)],
            'is_admin' => ['boolean'],
            'is_default' => ['boolean'],
            'position' => ['integer', 'between:0,999'],

            /* Şablon: yetenek anahtarlarının listesi. Süzme controller'da. */
            'template' => ['array'],
            'template.*' => ['string', 'max:60'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_admin' => $this->boolean('is_admin'),
            'is_default' => $this->boolean('is_default'),
            'position' => (int) $this->input('position', 0),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'slug' => 'kısa ad',
            'label' => 'rol adı',
            'description' => 'açıklama',
            'badge' => 'rozet rengi',
        ];
    }
}
