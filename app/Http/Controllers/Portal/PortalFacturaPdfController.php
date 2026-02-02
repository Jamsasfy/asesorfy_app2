<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FacturaPdfController;
use App\Models\Factura;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalFacturaPdfController extends Controller
{
    public function __invoke(Request $request, Factura $factura)
    {
        $user = $request->user();
        $clienteIds = $user?->clientes()->pluck('clientes.id')->all() ?? [];

        abort_if(empty($clienteIds), Response::HTTP_FORBIDDEN);

        // ✅ Seguridad: solo PDFs de facturas de SUS clientes
        abort_unless(
            in_array((int) $factura->cliente_id, array_map('intval', $clienteIds), true),
            Response::HTTP_FORBIDDEN
        );

        // ✅ Reutiliza el generador existente (admin), sin permisos de admin
        return app(FacturaPdfController::class)->generarPdf($factura);
    }
}
