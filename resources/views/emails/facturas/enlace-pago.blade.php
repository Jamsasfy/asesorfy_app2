@component('mail::message')
# Enlace para completar tu pago

Hola,

Te enviamos de nuevo el enlace para completar el pago de tu factura:

**Factura:** {{ $factura->serie ?? '' }}{{ $factura->numero_factura ?? '' }}  
**Importe:** {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €

@component('mail::button', ['url' => $urlPago])
Pagar ahora
@endcomponent

Si ya has realizado el pago recientemente, puedes ignorar este mensaje.

Gracias por confiar en AsesorFy.

@endcomponent
