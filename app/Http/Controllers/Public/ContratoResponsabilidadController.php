<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContratoResponsabilidad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ContratoResponsabilidadController extends Controller
{
    public function show(string $token)
    {
        $contrato = ContratoResponsabilidad::where('token', $token)
            ->with('cliente', 'asesor')
            ->firstOrFail();

        if ($contrato->esFirmado()) {
            return redirect()->route('responsabilidad.firmado', $token);
        }

        return view('public.responsabilidad.show', compact('contrato'));
    }

    public function firmar(string $token, Request $request)
    {
        $request->validate([
            'signature' => 'required|string',
        ]);

        $contrato = ContratoResponsabilidad::where('token', $token)
            ->with('cliente', 'asesor')
            ->firstOrFail();

        if ($contrato->esFirmado()) {
            return redirect()->route('responsabilidad.firmado', $token);
        }

        $signedAt = now();

        // 1. Generar PDF sin hash
        $pdf = Pdf::loadView('public.responsabilidad.pdf', [
            'contrato'         => $contrato,
            'signedAt'         => $signedAt,
            'signatureDataUri' => $request->input('signature'),
            'clientIp'         => $request->ip(),
            'hashFirma'        => null,
        ])->setPaper('a4');

        $fileName = 'responsabilidad/resp_' . $token . '_' . $signedAt->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        // 2. Calcular hash del PDF sin hash
        $hashFirma = hash_file('sha256', storage_path('app/public/' . $fileName));

        // 3. Regenerar PDF con hash incluido
        $pdfFinal = Pdf::loadView('public.responsabilidad.pdf', [
            'contrato'         => $contrato,
            'signedAt'         => $signedAt,
            'signatureDataUri' => $request->input('signature'),
            'clientIp'         => $request->ip(),
            'hashFirma'        => $hashFirma,
        ])->setPaper('a4');

        Storage::disk('public')->put($fileName, $pdfFinal->output());
        $absolutePdfPath = storage_path('app/public/' . $fileName);

        // 4. Guardar contrato
        $contrato->update([
            'signed_at'   => $signedAt,
            'ip_firma'    => $request->ip(),
            'pdf_path'    => $fileName,
            'hash_firma'  => $hashFirma,
            'email_firma' => $contrato->cliente->email_contacto,
        ]);

        // 4. Email al cliente con copia al asesor
        try {
            Mail::to($contrato->cliente->email_contacto)
                ->cc($contrato->asesor->email)
                ->send(new \App\Mail\ContratoResponsabilidadFirmadoMail($contrato, $absolutePdfPath));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar email contrato responsabilidad: ' . $e->getMessage());
        }

        // 5. Notificación al asesor
        try {
            \Filament\Notifications\Notification::make()
                ->title('✅ Contrato de responsabilidad firmado')
                ->body("El cliente {$contrato->cliente->razon_social} ha firmado el contrato de responsabilidad.")
                ->success()
                ->sendToDatabase($contrato->asesor);
        } catch (\Throwable $e) {
            Log::warning('No se pudo notificar al asesor: ' . $e->getMessage());
        }

        // Comentario firma
        $contrato->cliente->comentarios()->create([
            'user_id'   => $contrato->asesor_id,
            'contenido' => '✅ Doc. de exoneración firmado — "' . $contrato->titulo . '"',
        ]);

        return redirect()->route('responsabilidad.firmado', $token);
    }

    public function firmado(string $token)
    {
        $contrato = ContratoResponsabilidad::where('token', $token)
            ->with('cliente', 'asesor')
            ->firstOrFail();

        return view('public.responsabilidad.firmado', compact('contrato'));
    }
}
