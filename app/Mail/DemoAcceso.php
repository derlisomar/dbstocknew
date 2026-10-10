<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo con el usuario y la clave de la demo. */
class DemoAcceso extends Mailable
{
    public function __construct(
        public string $nombre,
        public string $usuario,
        public string $clave,
        public string $urlLogin,
        public string $vence,
        public string $empresa,
    ) {
    }

    public function envelope(): Envelope
    {
        $responder = (string) config('landing.correo_contacto');

        return new Envelope(
            subject: 'Tu acceso a la demo de dbstock',
            replyTo: $responder !== '' ? [new Address($responder, (string) config('landing.empresa'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-acceso');
    }
}
