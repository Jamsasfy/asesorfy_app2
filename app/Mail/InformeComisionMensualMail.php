<?php

namespace App\Mail;

use App\Models\ComercialHistorialObjetivo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InformeComisionMensualMail extends Mailable
{
    use Queueable, SerializesModels;

    public $historial;
    public $fecha;
    public $superaMinimos;

    public function __construct(ComercialHistorialObjetivo $historial)
    {
        $this->historial = $historial;
        $this->fecha = \Carbon\Carbon::create($historial->año, $historial->mes, 1);
        $this->superaMinimos = $historial->alcanzo_todos_minimos_obligatorios;
    }

    public function build()
    {
        $comercial = $this->historial->comercial;
        $nombreComercial = $comercial->trabajador->nombre ?? $comercial->name;
        $apellidosComercial = $comercial->trabajador->apellidos ?? '';

        $subject = $this->superaMinimos
            ? "Enhorabuena! Informe de Comisiones - {$this->fecha->locale('es')->isoFormat('MMMM YYYY')}"
            : "Informe de Comisiones - {$this->fecha->locale('es')->isoFormat('MMMM YYYY')}";

        $email = $this->subject($subject)
            ->view('emails.informe-comision-mensual')
            ->with([
                'nombreComercial' => trim("{$nombreComercial} {$apellidosComercial}"),
                'fecha'           => $this->fecha,
                'superaMinimos'   => $this->superaMinimos,
                'totalComisiones' => $this->historial->total_comisiones_calculado,
            ]);

        if ($this->historial->informe_pdf_path && Storage::disk('local')->exists($this->historial->informe_pdf_path)) {
            $email->attach(
                Storage::disk('local')->path($this->historial->informe_pdf_path),
                [
                    'as'   => 'informe-comisiones-' . $this->fecha->format('Y-m') . '.pdf',
                    'mime' => 'application/pdf',
                ]
            );
        }

        return $email;
    }
}
