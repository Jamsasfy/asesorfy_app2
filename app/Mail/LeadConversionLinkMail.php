<?php

// app/Mail/LeadConversionLinkMail.php

namespace App\Mail;

use App\Models\Lead;
use App\Models\LeadConversionLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadConversionLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public LeadConversionLink $link;

    public function __construct(Lead $lead, LeadConversionLink $link)
    {
        $this->lead = $lead;
        $this->link = $link;
    }

    public function build()
    {
        return $this->subject('Completa tu alta con AsesorFy')
            ->view('emails.leads.conversion-link');
    }
}
