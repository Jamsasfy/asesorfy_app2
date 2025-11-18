<?php

namespace App\Console\Commands;

use App\Mail\DailyLeadAutoEmailReportMail;
use App\Models\LeadAutoEmailLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarInformeEmailsAutomaticosCommand extends Command
{
    protected $signature = 'leads:enviar-informe-emails-diario';
    protected $description = 'Envía por email el informe diario de los envíos automáticos de leads (Boot IA Fy)';

    public function handle(): int
    {
        // Informe del día anterior
        $date = Carbon::yesterday();
        $fechaBusqueda = $date->format('Y-m-d');

        // 1) Cargar logs del día
        $logs = LeadAutoEmailLog::with('lead')
            ->whereDate('created_at', $fechaBusqueda)
            ->orderBy('created_at')
            ->get();

        $total        = $logs->count();
        $totalErrores = $logs->where('status', 'failed')->count();

        // 2) Resumen por estado
        $stats = $logs->groupBy('estado')->map(function ($items) {
            return [
                'total'        => $items->count(),
                'sent'         => $items->where('status', 'sent')->count(),
                'rate_limited' => $items->where('status', 'rate_limited')->count(),
                'skipped'      => $items->where('status', 'skipped')->count(),
                'failed'       => $items->where('status', 'failed')->count(),
            ];
        })->toArray();

        // 3) Generar PDF (👈 vista en reports/, no en pdf/)
        $pdf = Pdf::loadView('reports.leads_auto_emails_daily_pdf', [
            'date'         => $date,
            'logs'         => $logs,
            'stats'        => $stats,
            'total'        => $total,
            'totalErrores' => $totalErrores,
        ])->setPaper('a4', 'landscape');

        $pdfBinary = $pdf->output();

        // 4) Enviar email
        $toEmail = 'info@asesorfy.net';

        Mail::to($toEmail)->send(
            new DailyLeadAutoEmailReportMail(
                $date,
                $stats,
                $total,
                $totalErrores,
                $logs,      // se lo pasamos también al mailable
                $pdfBinary,
            )
        );

        $this->info("Informe diario enviado a {$toEmail}. Registros: {$total} | Errores: {$totalErrores}");

        return self::SUCCESS;
    }
}
