<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\System;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kutucuk ekleme ve düzenleme.
 *
 * Grup, şerit ve durum SERBEST METİN DEĞİLDİR: çizim tarafı yalnızca tanımlı
 * değerleri tanıyor, uydurma bir şerit adı kartı sessizce boş bırakırdı.
 */
class SystemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ad' => ['required', 'string', 'max:120'],
            'aciklama' => ['nullable', 'string', 'max:190'],
            'adres' => ['nullable', 'string', 'max:190'],
            'kod' => ['nullable', 'string', 'max:20'],

            /* Renk kartın ışığını belirliyor; biçimi sabit. */
            'renk' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            /*
             * Salonlar kayıttır (bkz. Grup): ayrı bir sabit liste tutulsaydı
             * ekleme ekranında görünen bir salon sayfada hiç çizilmeyebilirdi.
             */
            'grup' => ['required', Rule::exists('gruplar', 'anahtar')],
            'serit' => ['nullable', Rule::in(array_keys(config('system.lanes')))],

            'yan_etiket' => ['nullable', 'string', 'max:40', 'required_with:yan_adres'],
            'yan_adres' => ['nullable', 'string', 'max:190', 'required_with:yan_etiket'],

            'sira' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'etkin' => ['nullable', 'boolean'],

            /*
             * GÖRÜNÜRLÜK: kutucuğu kim görecek.
             *
             * "yetkili" seçildiğinde kutucuk, yetkisi olmayana HİÇ görünmez —
             * sönük ya da kilitli değil, listede yok. Bazı sistemlerin varlığı
             * bile herkesi ilgilendirmiyor.
             */
            /*
             * `sometimes`: alan GELMEZSE mevcut değer korunur.
             *
             * `required` olsaydı bu alanı taşımayan her yol (eski bir form,
             * bir konsol komutu) kaydı düzenleyemezdi; `nullable` olsaydı
             * alanı taşımayan bir gönderim "yetkili" bir kutucuğu sessizce
             * herkese açardı. İkisi de yanlış — yokluk "dokunma" demek.
             */
            'gorunurluk' => ['sometimes', Rule::in(System::VISIBILITIES)],

            /*
             * Anahtar, yetki adının okunur yarısıdır: "sistem-erisim.<anahtar>".
             * Boş bırakılırsa MODEL addan türetir (bkz. System::booted); burada
             * yalnızca elle girilen değer doğrulanır.
             */
            'anahtar' => [
                'nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('sistemler', 'anahtar')->ignore($this->route('system')?->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'ad' => 'ad', 'aciklama' => 'açıklama', 'adres' => 'adres', 'kod' => 'kod',
            'renk' => 'renk', 'grup' => 'grup', 'serit' => 'şerit',
            'yan_etiket' => 'yan bağlantı etiketi', 'yan_adres' => 'yan bağlantı adresi',
            'sira' => 'sıra', 'gorunurluk' => 'görünürlük', 'anahtar' => 'anahtar',
        ];
    }

    /** @return array<string, mixed> */
    public function kayit(): array
    {
        return [
            ...$this->safe()->except('etkin'),
            'sira' => $this->integer('sira'),
            'etkin' => $this->boolean('etkin'),
        ];
    }
}
