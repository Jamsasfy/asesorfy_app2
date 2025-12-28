<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Response;
use App\Models\Factura; // Importa el modelo Factura
use App\Services\ConfiguracionService; // Importa tu servicio de configuración
use Barryvdh\DomPDF\Facade\Pdf; // Importa la fachada de DomPDF
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Log; 

class FacturaPdfController extends Controller
{
    /**
     * Genera el PDF de una factura específica.
     *
     * @param Factura $factura La instancia de la factura a generar.
     * @return Response
     */
    public function generarPdf(Factura $factura)
    {
        try {
            // Cargar las relaciones necesarias para el PDF (cliente, items, servicio de items)
            $factura->load(['cliente', 'items.servicio']);

            // --- CÁLCULO DEL DESGLOSE DEL IVA ---
           $ivaBreakdown = [];

foreach ($factura->items as $item) {
    $itemPorcentajeIva = (float) $item->porcentaje_iva;
    $itemSubtotal = (float) $item->subtotal;

    $ivaRate = number_format($itemPorcentajeIva, 2, '.', ''); // clave del desglose
    $itemIvaAmount = $itemSubtotal * ($itemPorcentajeIva / 100);

    if (!isset($ivaBreakdown[$ivaRate])) {
        $ivaBreakdown[$ivaRate] = 0;
    }

    $ivaBreakdown[$ivaRate] += $itemIvaAmount;
}

ksort($ivaBreakdown);

            // --- FIN CÁLCULO DEL DESGLOSE DEL IVA ---

            // --- ¡BLOQUE DE DEPURACIÓN CRUCIAL! (Comentado, pero puedes descomentarlo si lo necesitas) ---
            // dd($ivaBreakdown); 
            // --- FIN BLOQUE DE DEPURACIÓN ---

            // Obtener la ruta RELATIVA del logo principal desde la configuración
            $relativePathLogo = ConfiguracionService::get('empresa_logo_url', 'images/logo.png');
            $relativePathLogo = trim($relativePathLogo, '/\\'); 
            $rutaLogoAbsoluta = public_path() . DIRECTORY_SEPARATOR . $relativePathLogo;

            // Obtener la ruta ABSOLUTA para la imagen del footer
            $rutaFooterImagen = public_path('images/digital-lovers-factura.jpg'); // <-- ¡NUEVO!

            // Recopilar todos los datos de la empresa para la plantilla
            $empresa = [
                'razon_social'      => ConfiguracionService::get('empresa_razon_social', 'Asesorfy S.L.'),
                'cif'               => ConfiguracionService::get('empresa_cif', 'B12345678'),
                'direccion_calle'   => ConfiguracionService::get('empresa_direccion_calle', 'C/ Ficticia, 123'),
                'direccion_cp'      => ConfiguracionService::get('empresa_direccion_cp', '11130'),
                'direccion_ciudad'  => ConfiguracionService::get('empresa_direccion_ciudad', 'Chiclana de la Frontera'),
                'direccion_provincia' => ConfiguracionService::get('empresa_direccion_provincia', 'Cádiz'),
                'direccion_pais'    => ConfiguracionService::get('empresa_direccion_pais', 'España'),
                'telefono'          => ConfiguracionService::get('empresa_telefono', '+34 956 123 456'),
                'email'             => ConfiguracionService::get('empresa_email', 'info@asesorfy.com'),
                'web'               => ConfiguracionService::get('empresa_web', 'https://www.asesorfy.com'),
                'logo_url'          => $rutaLogoAbsoluta, // <-- Ruta ABSOLUTA para el logo principal
                'banco_nombre'      => ConfiguracionService::get('empresa_banco_nombre', 'Banco Ficticio S.A.'),
                'banco_iban'        => ConfiguracionService::get('empresa_banco_iban', 'ESXX XXXX XXXX XXXX XXXX XXXX'),
                'banco_swift'       => ConfiguracionService::get('empresa_banco_swift', 'FICTESFFXXX'),
                'footer_image_url'  => $rutaFooterImagen, // <-- ¡Ruta ABSOLUTA para la imagen del footer!
            ];

            // Cargar la vista Blade con los datos de la factura y la empresa
            $pdf = Pdf::loadView('pdfs.factura', compact('factura', 'empresa', 'ivaBreakdown'));

            // Devolver el PDF al navegador para que lo abra en una nueva pestaña
            return $pdf->stream('factura_' . $factura->numero_factura . '.pdf');

        } catch (Exception $e) {
            // Registrar cualquier error que ocurra durante la generación del PDF
            Log::error("Error al generar PDF de la factura {$factura->id}: " . $e->getMessage());

            // Redirigir al usuario o mostrar un mensaje de error
            return redirect()->back()->with('error', 'No se pudo generar el PDF de la factura. Por favor, inténtelo de nuevo.');
        }
    }
}