@component('mail::message')
# Hemos recibido tu pago ✅

Hola,

Te confirmamos que hemos recibido correctamente el pago de tu factura:

**Factura:** {{ $factura->serie ?? '' }}{{ $factura->numero_factura ?? '' }}  
**Importe:** {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €

En las próximas horas tu asesor continuará con la gestión del servicio y recibirás la factura completa en tu email.

Gracias por confiar en AsesorFy.

@endcomponent
