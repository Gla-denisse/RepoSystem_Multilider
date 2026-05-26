<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InformeReporte extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreDestinatario,
        public string $nombreReporte,
        public string $pdfBinario,
        public string $nombreArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Informe: ' . $this->nombreReporte,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.informe_reporte',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinario, $this->nombreArchivo)
                ->withMime('application/pdf'),
        ];
    }
}
