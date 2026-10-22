<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\System;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Salon ekleme ve düzenleme.
 *
 * Yetkilendirme controller'ın ara katmanında; burada yalnızca biçim sınanır.
 */
class GroupRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ad' => ['required', 'string', 'max:60'],
            'not' => ['nullable', 'string', 'max:120'],
            'sira' => ['nullable', 'integer', 'min:0', 'max:999'],

            /*
             * Anahtar boş bırakılırsa MODEL addan türetir (bkz. Group::booted);
             * burada yalnızca elle girilen değer doğrulanır. Kutucuklar salonu
             * bu anahtarla tanıyor.
             */
            'anahtar' => [
                'nullable', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('gruplar', 'anahtar')->ignore($this->route('group')?->id),
            ],

            'ayri_sayfa' => ['nullable', 'boolean'],

            /* `sometimes`: alan gelmezse mevcut değer korunur (bkz. SystemRequest). */
            'gorunurluk' => ['sometimes', Rule::in(System::VISIBILITIES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ayri_sayfa' => $this->boolean('ayri_sayfa')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ad' => 'salon adı', 'not' => 'açıklama', 'sira' => 'sıra',
            'anahtar' => 'anahtar', 'ayri_sayfa' => 'ayrı sayfa', 'gorunurluk' => 'görünürlük',
        ];
    }
}
