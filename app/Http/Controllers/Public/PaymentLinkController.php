<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\EnlacePagoFacturaMail;

class PaymentLinkController extends Controller
{
    public function send(Factura $factura): RedirectResponse
    {
        if ($factura->estado !== FacturaEstadoEnum::PENDIENTE_PAGO) {
            return back()->with('error', 'Esta factura no está pendiente de pago.');
        }

        $cliente = $factura->cliente;

        if (! $cliente || ! $cliente->email_contacto) {
            return back()->with('error', 'El cliente no tiene email de contacto definido.');
        }

        $urlPago = route('payment.pay', $factura);

        try {
            Mail::to($cliente->email_contacto)
                ->send(new EnlacePagoFacturaMail($factura, $urlPago));

            return back()->with('success', 'Enlace de pago enviado correctamente al cliente.');
        } catch (\Exception $e) {
            Log::error('Error enviando enlace de pago: '.$e->getMessage());
            return back()->with('error', 'No se pudo enviar el enlace de pago. Revisa la configuración de correo.');
        }
    }
}
