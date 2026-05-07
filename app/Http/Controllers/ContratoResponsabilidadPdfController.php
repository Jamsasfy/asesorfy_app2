<?php

namespace App\Http\Controllers;

use App\Models\ContratoResponsabilidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ContratoResponsabilidadPdfController extends Controller
{
    /**
     * Sirve el PDF firmado de un ContratoResponsabilidad desde el
     * admin, con verificación de permiso Shield y estado.
     *
     * Se usa como endpoint para el botón "Ver PDF" del RelationManager
     * en ClienteResource. Reemplaza el acceso directo vía
     * Storage::disk('public')->url() que permitía descarga sin
     * autenticación.
     *
     * En Subfase 2B lee de disco 'public' (estado actual). En Fase 3
     * se migrará a disco 'local'.
     */
    public function __invoke(Request $request, ContratoResponsabilidad $contrato): Response
    {
        // Guard 1: permiso Shield
        abort_unless(
            $request->user()?->can('VerPdf:ContratoResponsabilidad'),
            403,
            'No tienes permiso para ver PDFs de contratos de responsabilidad.'
        );

        // Guard 2: el contrato debe estar firmado
        abort_if(
            is_null($contrato->signed_at),
            404,
            'Este contrato todavía no ha sido firmado.'
        );

        // Guard 3: pdf_path debe existir
        abort_if(
            blank($contrato->pdf_path),
            404,
            'Este contrato no tiene PDF asociado.'
        );

        $disk = Storage::disk('local');

        // Guard 4: el archivo debe existir físicamente
        abort_unless(
            $disk->exists($contrato->pdf_path),
            404,
            'El archivo PDF no se encuentra en el almacenamiento.'
        );

        // Nombre descargable sugerido
        $downloadName = 'contrato-responsabilidad-' . $contrato->id . '.pdf';

        return $disk->response(
            $contrato->pdf_path,
            $downloadName,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            ]
        );
    }
}
