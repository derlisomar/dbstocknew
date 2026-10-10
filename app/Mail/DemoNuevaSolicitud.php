<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso interno: alguien pidió una demo. No incluye la clave. */
class DemoNuevaSolicitud extends Mailable
{
    public function __construct(
        public string $nombre,
        public string $negocio,
        public string $email,
        public ?string $telefono,
        public string $vence,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nueva demo de dbstock: '.$this->negocio);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-aviso');
    }
}
