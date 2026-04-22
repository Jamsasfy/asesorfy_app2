<?php

namespace App\Mail;

use App\Models\ComercialContratoIncentivo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ContratoIncentivosComercialFirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComercialContratoIncentivo $contrato
    ) {}

    public function build()
    {
        $comercial = $this->contrato->comercial;

        $todosFirmados = $comercial->contratosIncentivos()
            ->whereNotNull('fecha_firma')
            ->whereNotNull('pdf_path')
            ->orderBy('fecha_firma', 'asc')
            ->get();

        $cantidadContratos = $todosFirmados->count();

        $subject = "Copia de tus Contratos de Incentivos Firmados ({$cantidadContratos}) - AsesorFy";

        $mail = $this->subject($subject)
            ->view('emails.contrato-incentivos-firmado')
            ->with([
                'comercial'         => $comercial,
                'contratos'         => $todosFirmados,
                'cantidadContratos' => $cantidadContratos,
            ]);

        foreach ($todosFirmados as $contrato) {
            $pdfPath = Storage::disk('local')->path($contrato->pdf_path);

            if (!file_exists($pdfPath)) {
                \Illuminate\Support\Facades\Log::warning("PDF no encontrado: {$pdfPath}");
                continue;
            }

            $tipoNombre  = $contrato->tipo === 'base' ? 'contrato-base' : 'anexo-' . $contrato->id;
            $fechaFormato = $contrato->fecha_firma->format('Y-m-d');

            $mail->attach($pdfPath, [
                'as'   => "{$tipoNombre}-firmado-{$fechaFormato}.pdf",
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
