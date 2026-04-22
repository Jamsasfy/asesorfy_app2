<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Comisiones</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                Informe de Comisiones
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #e0f2fe; font-size: 16px;">
                                {{ $fecha->locale('es')->isoFormat('MMMM [de] YYYY') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Saludo -->
                    <tr>
                        <td style="padding: 30px 40px 20px 40px;">
                            <p style="margin: 0; font-size: 16px; color: #1f2937;">
                                Hola <strong>{{ $nombreComercial }}</strong>,
                            </p>
                        </td>
                    </tr>

                    <!-- Contenido según resultado -->
                    @if($superaMinimos)
                        <!-- Mensaje de Enhorabuena -->
                        <tr>
                            <td style="padding: 0 40px;">
                                <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                    <p style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: #166534;">
                                        ¡Enhorabuena!
                                    </p>
                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #166534;">
                                        Has alcanzado los objetivos establecidos para este mes.
                                        Tu esfuerzo y dedicación han dado excelentes resultados.
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <!-- Total Comisiones -->
                        <tr>
                            <td style="padding: 0 40px 20px 40px;">
                                <table width="100%" cellpadding="15" cellspacing="0" style="background: #f9fafb; border-radius: 6px;">
                                    <tr>
                                        <td style="font-size: 14px; color: #6b7280;">
                                            <strong>Total de comisiones generadas:</strong>
                                        </td>
                                        <td align="right" style="font-size: 20px; font-weight: bold; color: #16a34a;">
                                            €{{ number_format($totalComisiones, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Información de pago -->
                        <tr>
                            <td style="padding: 0 40px 20px 40px;">
                                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #6b7280; background: #f0f9ff; padding: 15px; border-radius: 6px; border-left: 3px solid #0ea5e9;">
                                    <strong style="color: #0369a1;">Información de pago:</strong><br>
                                    Las comisiones se abonarán en la nómina del mes siguiente (a mes vencido),
                                    aplicándose las retenciones fiscales y deducciones de Seguridad Social
                                    correspondientes según la normativa vigente.
                                </p>
                            </td>
                        </tr>
                    @else
                        <!-- Mensaje de Objetivos No Alcanzados -->
                        <tr>
                            <td style="padding: 0 40px;">
                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                    <p style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: #92400e;">
                                        Objetivos no alcanzados
                                    </p>
                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                                        No se han alcanzado los mínimos establecidos este mes.
                                        Te invitamos a revisar el informe detallado adjunto para identificar
                                        áreas de mejora y trabajar en los objetivos del próximo periodo.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endif

                    <!-- Adjunto -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <p style="margin: 0 0 15px 0; font-size: 14px; color: #4b5563;">
                                Encontrarás el informe detallado adjunto a este correo con toda la información sobre:
                            </p>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #6b7280; line-height: 1.8;">
                                <li>Detalle completo de ventas del mes</li>
                                <li>Desglose por regla de comisión</li>
                                <li>Resumen de facturación y comisiones</li>
                            </ul>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f9fafb; padding: 25px 40px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 5px 0; font-size: 12px; color: #6b7280; text-align: center;">
                                <strong>AsesorFy S.L.</strong>
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #9ca3af; text-align: center; line-height: 1.5;">
                                Este es un email automático generado por el sistema de gestión comercial.<br>
                                Por favor, no respondas a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
