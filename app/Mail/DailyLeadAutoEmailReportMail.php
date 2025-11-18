<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyLeadAutoEmailReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $date;
    public array $stats;
    public int $total;
    public int $totalErrores;
    public $logs; // Collection o array

    protected string $pdfBinary;

    /**
     * @param Carbon                $date
     * @param array                 $stats
     * @param int                   $total
     * @param int                   $totalErrores
     * @param \Illuminate\Support\Collection|array $logs
     * @param string                $pdfBinary
     */
    public function __construct(
        Carbon $date,
        array $stats,
        int $total,
        int $totalErrores,
        $logs,
        string $pdfBinary
    ) {
        $this->date         = $date;
        $this->stats        = $stats;
        $this->total        = $total;
        $this->totalErrores = $totalErrores;
        $this->logs         = $logs;
        $this->pdfBinary    = $pdfBinary;
    }

    public function build(): self
    {
        $fileName = 'informe-emails-automaticos-' . $this->date->format('Y-m-d') . '.pdf';

        return $this
            ->subject('Informe diario Boot IA – ' . $this->date->format('d/m/Y'))
            ->view('emails.reports.leads_auto_emails_daily', [
                'date'         => $this->date,
                'stats'        => $this->stats,
                'total'        => $this->total,
                'totalErrores' => $this->totalErrores,
                'logs'         => $this->logs, // 👈 ahora la vista SIEMPRE lo tiene
            ])
            ->attachData($this->pdfBinary, $fileName, [
                'mime' => 'application/pdf',
            ]);
    }
}
