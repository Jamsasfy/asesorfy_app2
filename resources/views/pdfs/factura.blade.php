<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura {{ $factura->numero_factura }}</title>
    <style>
        body {
            font-family: 'Varela Round', 'Arial', sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 8mm; /* Márgenes reducidos */
        }
        
        .header-section {
            width: 100%;
            margin-bottom: 20px;
        }

        .header-left, .header-right {
            display: inline-block;
            vertical-align: top;
            width: 49%;
            box-sizing: border-box;
        }

        .header-left {
            float: left;
            padding-right: 10px;
        }
        .header-right {
            float: right;
            text-align: right;
        }

        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }
        .empresa-info, .factura-info {
            font-size: 10pt;
            line-height: 1.4;
        }
        .empresa-info strong, .factura-info strong {
            display: block;
        }
        h1 {
            font-size: 18pt;
            margin: 0 0 10px 0;
            padding: 0;
            color: #333;
        }
        .clear {
            clear: both;
        }
        .cliente-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            font-size: 9pt;
            line-height: 1.5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        /* Aplicar el color azul claro al fondo de los encabezados de tabla (th) */
        th {
            background-color: #d2eff8; /* Azul claro para los encabezados */
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            width: 40%; 
            float: right;
        }
        .totals table {
            margin-top: 0;
        }
        .totals td {
            border: none;
            padding: 5px 8px;
        }
        .totals .label {
            font-weight: bold;
        }
        .totals .total-final {
            font-size: 12pt; /* Letra más pequeña para el total final */
            font-weight: bold;
            background-color: #d2eff8; /* Fondo azul claro para el total final */
        }
        .totals .total-final .label {
            font-size: 12pt; /* Letra más pequeña para el label del total final */
        }
        .notes, .bank-info {
            font-size: 9pt;
            color: #555;
           
        }
        .bank-info {
            float: left;
            width: 55%;
             margin-top: 40px;
        }
        .footer-fixed {
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0mm 0mm;
            font-size: 8pt;
            color: #555;
            text-align: center;
            border-top: 1px solid #eee;
            background-color: white;
            box-sizing: border-box;
            /* CAMBIO: Añadir margen superior para separar del contenido y de la imagen de abajo */
            padding-top: 5mm; 
        }

        .footer-image { /* Estilo para la nueva imagen del footer */
            max-width: 400px; /* Ajusta el tamaño de la imagen según sea necesario */
            height: auto;
            margin-top: 5mm; /* Espacio entre el texto del footer y la imagen */
            display: block; /* Para centrarla si text-align es center */
            margin-left: auto; /* Para centrar la imagen */
            margin-right: auto; /* Para centrar la imagen */
        }

        /* Puedes eliminar la clase .bg-light-blue si ya no se usa en ningún 'td' */
        /* .bg-light-blue {
            background-color: #d2eff8; 
        } */

        /* Anchos de columna para la tabla de ítems */
        .col-desc { width: 50%; } /* Descripción más ancha */
        .col-qty { width: 8%; } /* Cantidad más estrecha */
        .col-price { width: 15%; } /* Precio Unit. */
        .col-discount { width: 12%; } /* Descuento */
        .col-subtotal { width: 15%; } /* Subtotal */

        /* Para evitar que los strings largos se rompan en los encabezados */
        .td-shrink {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="header-left">
            @if (!empty($empresa['logo_url']) && file_exists($empresa['logo_url']))
                <img src="{{ $empresa['logo_url'] }}" class="logo">
            @else
                <div style="width: 150px; height: 50px; background-color: #f0f0f0; text-align: center; line-height: 50px; border: 1px dashed #ccc;">Logo Asesorfy</div>
            @endif
            <div class="empresa-info">
                <strong>{{ $empresa['razon_social'] ?? 'Nombre de la Empresa' }}</strong><br>
                {{ $empresa['direccion_calle'] ?? '' }}<br>
                {{ $empresa['direccion_cp'] ?? '' }} {{ $empresa['direccion_ciudad'] ?? '' }} ({{ $empresa['direccion_provincia'] ?? '' }})<br>
                {{ $empresa['pais'] ?? 'España' }}<br>
                CIF: {{ $empresa['cif'] ?? '' }}<br>
                Tel: {{ $empresa['telefono'] ?? '' }}<br>
                Email: {{ $empresa['email'] ?? '' }}<br>
                Web: {{ $empresa['web'] ?? '' }}
            </div>
        </div>
        <div class="header-right">
            <h1>FACTURA</h1>
            <strong>Nº Factura:</strong> {{ $factura->numero_factura }}<br>
            <strong>Fecha Emisión:</strong> {{ $factura->fecha_emision->format('d/m/Y') }}<br>
            <strong>Fecha Vencimiento:</strong> {{ $factura->fecha_vencimiento->format('d/m/Y') }}<br>
            <strong>Estado:</strong> {{ $factura->estado->getLabel() }}
        </div>
        <div class="clear"></div>
    </div>

    <div class="cliente-info">
        <strong>DATOS DEL CLIENTE</strong><br>
        @if($factura->cliente->tipo_cliente_id == 1)
            {{-- AUTÓNOMO: Nombre completo, nombre comercial (si existe) y DNI --}}
            <strong>Nombre y Apellidos:</strong> {{ $factura->cliente->nombre }} {{ $factura->cliente->apellidos }}<br>
            @if ($factura->cliente->nombre_comercial && $factura->cliente->nombre_comercial !== ($factura->cliente->nombre . ' ' . $factura->cliente->apellidos))
                <strong>Nombre comercial:</strong> {{ $factura->cliente->nombre_comercial }}<br>
            @endif
            <strong>DNI/NIF:</strong> {{ $factura->cliente->dni_cif }}<br>
        @else
            {{-- SOCIEDAD: Razón social, nombre comercial (si existe) y CIF --}}
            <strong>Razón Social:</strong> {{ $factura->cliente->razon_social }}<br>
            @if ($factura->cliente->nombre_comercial && $factura->cliente->nombre_comercial !== $factura->cliente->razon_social)
                <strong>Nombre comercial:</strong> {{ $factura->cliente->nombre_comercial }}<br>
            @endif
            <strong>CIF:</strong> {{ $factura->cliente->dni_cif }}<br>
        @endif
        @if ($factura->cliente->direccion)
            {{ $factura->cliente->direccion }}<br>
        @endif
        @if ($factura->cliente->codigo_postal || $factura->cliente->localidad || $factura->cliente->provincia)
            {{ $factura->cliente->codigo_postal ?? '' }} {{ $factura->cliente->localidad ?? '' }} ({{ $factura->cliente->provincia ?? '' }})<br>
        @endif
        {{ $factura->cliente->pais ?? 'España' }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-desc">Descripción</th>
                <th class="col-qty text-right">Cantidad</th>
                <th class="col-price text-right">Precio Unit.</th>
                <th class="col-discount text-right">Dto</th>
                <th class="col-subtotal text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
    @foreach ($factura->items as $item)
        @php
            $leyendaDescuento = null;
            if ($item->descuento_tipo && $item->descuento_valor) {
                $leyendaDescuento = match($item->descuento_tipo) {
                    'porcentaje' => $item->descuento_valor . ' %',
                    'fijo' => '-' . number_format($item->descuento_valor, 2, ',', '.') . ' €',
                    'precio_final' => 'precio final',
                    default => null
                };
            }
        @endphp
        <tr>
            <td>
                {{ $item->descripcion }}
            </td>
            <td class="text-right">
                {{ number_format($item->cantidad, 2, ',', '.') }}
            </td>
            <td class="text-right">
                {{ number_format($item->precio_unitario, 2, ',', '.') }} €
            </td>
            <td class="text-right">
                {{ $leyendaDescuento ?? '-' }}
            </td>
            <td class="text-right">
                {{ number_format($item->subtotal, 2, ',', '.') }} €
            </td>
        </tr>
    @endforeach
</tbody>
    </table>

    <div class="footer-sections">
        <div class="bank-info">
            @if ($factura->metodo_pago === 'transferencia')
                <strong>Datos Bancarios para Transferencia:</strong><br>
                Banco: {{ $empresa['banco_nombre'] ?? '' }}<br>
                IBAN: {{ $empresa['banco_iban'] ?? '' }}<br>
                SWIFT/BIC: {{ $empresa['banco_swift'] ?? '' }}
            @elseif ($factura->estado === App\Enums\FacturaEstadoEnum::PAGADA)
                <strong>Estado de Pago:</strong> Pago Recibido<br>
                Método: {{ $factura->metodo_pago ?? 'N/A' }}
            @else
                <strong>Método de Pago:</strong> {{ $factura->metodo_pago ?? 'No especificado' }}
            @endif
        </div>

        <div class="totals">
            <table>
                <tr>
                    <td class="label">Base Imponible:</td>
                    <td class="text-right">{{ number_format($factura->base_imponible, 2, ',', '.') }} €</td>
                </tr>
                @if (!empty($ivaBreakdown))
                    <tr>
                        <td colspan="2" style="padding-top: 10px; font-weight: bold;">
                            @if (count($ivaBreakdown) === 1)
                                IVA Aplicado:
                            @else
                                Desglose IVA:
                            @endif
                        </td>
                    </tr>
                    @foreach ($ivaBreakdown as $rate => $amount)
                        <tr>
                            <td class="label" style="font-size: 9pt;">IVA {{ number_format($rate, 2, ',', '.') }}%:</td>
                            <td class="text-right" style="font-size: 9pt;">{{ number_format($amount, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                @endif
                <tr class="total-final">
                    <td class="label">TOTAL FACTURA:</td>
                    <td class="text-right">{{ number_format($factura->total_factura, 2, ',', '.') }} €</td>
                </tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>
    
    <div class="notes">
        <p>Este documento es confidencial y está protegido por la normativa vigente de protección de datos. El impago de esta factura, según los términos y condiciones aceptados, podrá suponer la suspensión o cancelación del servicio, así como la asunción de responsabilidades legales derivadas.

El servicio podrá reactivarse únicamente tras la regularización del pago pendiente. AsesorFy se reserva el derecho a no prestar nuevos servicios hasta la resolución del impago.

Para más información, consulta nuestras condiciones en www.asesorfy.net.</p>
        @if ($factura->observaciones_publicas)
            <p>Observaciones: {{ $factura->observaciones_publicas }}</p>
        @endif
    </div>

    <div class="footer-fixed">
        <p>{{ $empresa['razon_social'] ?? 'Asesorfy' }} - CIF: {{ $empresa['cif'] ?? 'B12345678' }} - {{ $empresa['email'] ?? 'info@asesorfy.net' }} - {{ $empresa['telefono'] ?? '722873562' }} - {{ $empresa['web'] ?? 'https://www.asesorfy.net' }}</p>
        @if (!empty($empresa['footer_image_url']) && file_exists($empresa['footer_image_url']))
            <img src="{{ $empresa['footer_image_url'] }}" class="footer-image">
        @endif
    </div>
</body>
</html>