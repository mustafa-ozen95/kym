<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Posta ayarlarının çalıştığını kanıtlayan ileti.
 *
 * KUYRUĞA ALINMAZ: yönetici sonucu ŞİMDİ görmek için basıyor. Kuyruğa
 * alınsaydı ekran "gönderildi" derdi, hata ise günlüğe düşer ve ayarın
 * bozukluğu ancak gerçek bir doğrulama postası kaybolduğunda anlaşılırdı.
 */
class TestMessage extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — deneme iletisi');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test', with: ['zaman' => now()->format('d.m.Y H:i')]);
    }
}
