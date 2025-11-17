<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadGenericTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectText;
    public string $bodyHtml;
    public ?string $ctaText;
    public ?string $ctaUrl;

    public function __construct(string $subjectText, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null)
    {
        $this->subjectText = $subjectText;
        $this->bodyHtml    = $bodyHtml;
        $this->ctaText     = $ctaText;
        $this->ctaUrl      = $ctaUrl;
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectText)
            ->view('emails.layouts.base', [
                'subject'  => $this->subjectText,
                'bodyHtml' => $this->bodyHtml,
                'ctaText'  => $this->ctaText,
                'ctaUrl'   => $this->ctaUrl,
            ]);
    }
}
