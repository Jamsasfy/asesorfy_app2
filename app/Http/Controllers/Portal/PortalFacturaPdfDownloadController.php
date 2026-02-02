<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FacturaPdfController;
use App\Models\Factura;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalFacturaPdfDownloadController extends Controller
{
    public function __invoke(Request $request, Factura $factura)
    {
        $user = $request->user();
        $clienteIds = $user?->clientes()->pluck('clientes.id')->all() ?? [];

        abort_if(empty($clienteIds), Response::HTTP_FORBIDDEN);

        abort_unless(
            in_array((int) $factura->cliente_id, array_map('intval', $clienteIds), true),
            Response::HTTP_FORBIDDEN
        );

        $response = app(FacturaPdfController::class)->generarPdf($factura);

        // ✅ Forzar descarga
        $filename = 'factura-' . ($factura->numero_factura ?: $factura->id) . '.pdf';
        $disposition = 'attachment; filename="' . $filename . '"';

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
