<?php

declare(strict_types=1);

namespace App\Http\Requests\Yonetim;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bağlı uygulama isteği.
 *
 * Yetkilendirme controller'ın ara katmanında; burada yalnızca biçim sınanır.
 *
 * DÖNÜŞ ADRESİ https OLMAK ZORUNDA. Kod adres çubuğunda taşınıyor; şifresiz
 * bir adrese gönderilmesi, aradaki herkese kodu vermek demektir. Yerel
 * geliştirme için http istenirse burası bilinçli olarak gevşetilir, sessizce
 * değil.
 */
class IstemciRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            /*
             * Ad, üretilen kurulum dosyasına (PHP) girer. Dosya ad'ı veri
             * olarak basıyor (bkz. KurulumDosyasi) ama ikinci savunma olarak
             * yorumu kapatan, tırnak açan ve satır bölen karakterler baştan
             * reddedilir: ad bir etikettir, kod taşımaz.
             */
            'name' => ['required', 'string', 'max:80', 'not_regex:/\*\/|["\'`\\\\\r\n]/'],

            /* Birden çok adres satır satır yazılır; boş satırlar atılır. */
            'adresler' => ['required', 'string', 'max:2000'],

            /*
             * GİREBİLEN ROLLER. Boş liste = herkes; kısıt eklemek bilinçli
             * bir adımdır. Değerler rol uuid'leridir: sıralı kimlik dışarı
             * çıkmaz.
             */
            'roller' => ['nullable', 'array'],
            'roller.*' => ['string', 'exists:roller,uuid'],

            'otomatik_hesap' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Metin kutusundan gelen adresleri listeye çevirir ve doğrular.
     *
     * @return list<string>
     */
    public function adresler(): array
    {
        $liste = [];

        foreach (preg_split('/\R/', (string) $this->input('adresler')) ?: [] as $satir) {
            $adres = trim($satir);

            if ($adres !== '') {
                $liste[] = $adres;
            }
        }

        return $liste;
    }

    protected function passedValidation(): void
    {
        $hatali = array_filter(
            $this->adresler(),
            fn (string $adres): bool => ! filter_var($adres, FILTER_VALIDATE_URL)
                || ! str_starts_with($adres, 'https://')
                /*
                 * FILTER_VALIDATE_URL tırnağı geçirir; adres üretilen PHP
                 * dosyasına sabit olarak girdiği için tırnak, ters bölü ve
                 * denetim karakterleri adreste YERİ YOK.
                 */
                || preg_match('/["\'`\\\\\x00-\x20\x7f]/', $adres) === 1,
        );

        if ($this->adresler() === [] || $hatali !== []) {
            $this->validator->errors()->add(
                'adresler',
                'Her dönüş adresi https ile başlayan tam bir adres olmalı (ör. https://ornek.ornek.test/giris/merkez/geri).',
            );

            $this->failedValidation($this->validator);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'uygulama adı', 'adresler' => 'dönüş adresleri'];
    }
}
