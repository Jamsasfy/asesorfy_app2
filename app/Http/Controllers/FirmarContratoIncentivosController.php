<?php

namespace App\Http\Controllers;

use App\Models\ComercialContratoIncentivo;
use App\Services\ContratoIncentivosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FirmarContratoIncentivosController extends Controller
{
    public function show(string $token)
    {
        $contrato = ComercialContratoIncentivo::where('token_firma', $token)->firstOrFail();

        if ($contrato->estaFirmado()) {
            return view('public.firma-contrato-incentivos.firmado', [
                'contrato' => $contrato,
            ]);
        }

        return view('public.firma-contrato-incentivos.form', [
            'contrato' => $contrato,
            'pdfUrl'   => route('contrato-incentivos.pdf', ['token' => $token]),
        ]);
    }

    public function firmar(Request $request, string $token)
    {
        $request->validate([
            'firma' => 'required|string',
        ]);

        $contrato = ComercialContratoIncentivo::where('token_firma', $token)->firstOrFail();

        if ($contrato->estaFirmado()) {
            return response()->json([
                'success' => false,
                'message' => 'Este contrato ya ha sido firmado.',
            ], 400);
        }

        try {
            $service = new ContratoIncentivosService();

            $service->firmarContrato(
                $contrato,
                $request->input('firma'),
                $request->ip(),
                $request->userAgent()
            );

            Log::info("Contrato firmado — ID: {$contrato->id}, Comercial: {$contrato->comercial->name}");

            // Refrescar para obtener pdf_path actualizado
            $contrato->refresh();
            $this->enviarCopiaFirmada($contrato);

            return response()->json([
                'success'  => true,
                'message'  => 'Contrato firmado correctamente.',
                'redirect' => route('firmar-contrato-incentivos', ['token' => $token]),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al firmar el contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function enviarCopiaFirmada(ComercialContratoIncentivo $contrato): void
    {
        try {
            Mail::to($contrato->comercial->email)
                ->send(new \App\Mail\ContratoIncentivosComercialFirmadoMail($contrato));

            Log::info("Copia de contrato firmado enviada — ID: {$contrato->id}, Comercial: {$contrato->comercial->name}");
        } catch (\Exception $e) {
            Log::error("Error enviando copia de contrato firmado: " . $e->getMessage());
        }
    }
}
