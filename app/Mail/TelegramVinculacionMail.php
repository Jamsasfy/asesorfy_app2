<?php

namespace App\Mail;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TelegramVinculacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public string $url,
    ) {}

    public function build()
    {
        return $this
            ->subject('Vincula tu Telegram con AsesorFy')
            ->view('emails.telegram.vinculacion');
    }
}
