<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\LeadConversionLink;
use App\Mail\LeadConversionLinkMail;
use App\Models\LeadAutoEmailLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Filament\Actions;
use Filament\Forms\Components\Radio;
use App\Models\Venta;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    // 1. Propiedad temporal para guardar la elección del modal
    public ?string $pagoInicialSeleccionado = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // 2. Método para inyectar el dato antes de crear el registro
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si el usuario seleccionó un método de pago en el modal, lo añadimos aquí
        if ($this->pagoInicialSeleccionado) {
            $data['pago_inicial_metodo'] = $this->pagoInicialSeleccionado;
        }

        return $data;
    }

    protected function getFormActions(): array
{
    // 1. Definimos una función helper para saber si toca pedir pago inicial
    // Esto revisa los items que el usuario ha añadido al formulario antes de guardar.
    $necesitaPagoInicial = function () {
        $items = $this->data['items'] ?? []; // Asumo que tu repeater se llama 'items'
        
        if (empty($items)) return false;

        $ids = collect($items)->pluck('servicio_id')->filter();
        
        // Consultamos si alguno de los servicios seleccionados es de tipo 'unico'
        // AJUSTA 'unico' al valor real de tu ENUM o base de datos
        return \App\Models\Servicio::whereIn('id', $ids)
            ->where('tipo', 'unico') 
            ->exists();
    };

    return [
        Actions\Action::make('guardar')
            ->label('Guardar')
            ->color('primary')
            
            // 2. Condicionamos el Título. Si devolvemos false/null, Filament intenta saltarse el modal
            ->modalHeading(fn() => $necesitaPagoInicial() ? 'Método de pago inicial' : false)
            
            // 3. Condicionamos la descripción
            ->modalDescription(fn() => $necesitaPagoInicial() ? '¿Cómo ha acordado el cliente que va a pagar los servicios ÚNICOS de esta venta?' : null)
            
            // 4. Condicionamos el formulario. Si devuelve [], no hay campos que mostrar.
            ->form(function () use ($necesitaPagoInicial) {
                if (! $necesitaPagoInicial()) {
                    return []; // Array vacío = No mostrar campos
                }

                return [
                    Radio::make('pago_inicial_metodo_modal')
                        ->label('Método de pago inicial')
                        ->options([
                            'tarjeta'       => 'Tarjeta (pago online)',
                            'transferencia' => 'Transferencia bancaria',
                        ])
                        ->required(),
                ];
            })
            
            ->modalSubmitActionLabel('Confirmar y guardar')
            ->modalWidth('md')
            
            ->action(function (array $data) {
                // 5. Lógica de guardado
                // Si el modal no se mostró, $data estará vacío, así que usamos null.
                $this->pagoInicialSeleccionado = $data['pago_inicial_metodo_modal'] ?? null;
                
                $this->create();
            }),

        Actions\Action::make('cancelar')
            ->label('Cancelar')
            ->color('gray')
            ->url($this->getResource()::getUrl('index')),
    ];
}
    private function buildSaleBlueprint(Venta $venta): array
{
    $venta->loadMissing('items.servicio');

    $blueprint = [];

    foreach ($venta->items as $item) {
        $svc = $item->servicio;

        $nombreOriginal = $item->nombre_personalizado ?: ($svc->nombre ?? '');
        $nombre = strtoupper($nombreOriginal);

        $esAlta = str_contains($nombre, 'ALTA') && (str_contains($nombre, 'AUTON') || str_contains($nombre, 'AUTÓN'));
        $esSL   = str_contains($nombre, 'SOCIEDAD') || str_contains($nombre, 'SL');
        $esCap  = str_contains($nombre, 'CAPITALIZA') || str_contains($nombre, 'PAGO ÚNICO');

        $blueprint[] = [
            'servicio_id'         => $svc->id,
            'nombre'              => $nombreOriginal,
            'nombre_normalizado'  => $nombre,
            'tipo'                => $svc->tipo->value,
            'precio_base'         => $item->precio_unitario_aplicado ?? $item->precio_unitario,
            'unidades'            => $item->cantidad,
            'total_linea'         => $item->subtotal_aplicado,
            'es_tarifa_principal' => $svc->es_tarifa_principal,
            'es_alta_autonomo'     => $esAlta,
            'es_creacion_sociedad' => $esSL,
            'es_capitalizacion'    => $esCap,
        ];
    }

    \Log::info("🧩 BLUEPRINT GENERADO UNIFICADO:", $blueprint);

    return $blueprint;
}

protected function afterCreate(): void
{
    /** @var Venta|null $venta */
    $venta = $this->record;

    if (!$venta) {
        return;
    }

    // 1) Recalcular total
    $venta->updateTotal();

    // 2) Si falta lead o cliente → no enviamos contrato
    if (!$venta->lead_id || !$venta->lead || !$venta->cliente) {
        return;
    }

    // 3) Si el lead ya firmó un contrato, no enviar nada
    if ($venta->lead->contract_signed_at) {
        return;
    }

    // 4) Crear blueprint unificado
    $itemsBlueprint = $this->buildSaleBlueprint($venta);

    if (empty($itemsBlueprint)) {
        Notification::make()
            ->title('Venta sin servicios')
            ->body('No se ha podido enviar el contrato porque la venta no tiene servicios.')
            ->danger()
            ->send();
        return;
    }

    // 5) Detectar formulario adecuado
    $formType = 'alta_autonomo_fiscal_recurrente';

    foreach ($itemsBlueprint as $bpItem) {

        if (!empty($bpItem['es_creacion_sociedad'])) {
            $formType = 'creacion_sociedad';
            continue;
        }

        if (!empty($bpItem['es_capitalizacion']) && $formType !== 'creacion_sociedad') {
            $formType = 'capitalizacion';
            continue;
        }

        if (!empty($bpItem['es_alta_autonomo']) &&
            $formType === 'alta_autonomo_fiscal_recurrente') {
            $formType = 'alta_autonomo';
        }
    }

    // 6) Prefill de datos del cliente
    $cliente = $venta->cliente;

    $formData = [
        'nombre'             => $cliente->nombre ?? '',
        'apellidos'          => $cliente->apellidos ?? '',
        'dni'                => $cliente->dni_cif,
        'email'              => $cliente->email_contacto,
        'telefono'           => $cliente->telefono_contacto,
        'direccion'          => $cliente->direccion,
        'cp'                 => $cliente->codigo_postal,
        'localidad'          => $cliente->localidad,
        'provincia'          => $cliente->provincia,
        'comunidad_autonoma' => $cliente->comunidad_autonoma,
        'cuenta_bancaria_ss' => $cliente->iban_asesorfy,
        'tipo_cliente_id'    => $cliente->tipo_cliente_id,
    ];

    // 7) Crear link de firma (manual)
    $link = \App\Models\LeadConversionLink::create([
        'lead_id'    => $venta->lead_id,
        'token'      => \Illuminate\Support\Str::uuid(),
        'expires_at' => now()->addDays(15),
        'mode'       => 'manual', // ⬅ IMPORTANTE
        'meta'       => [
            'form_type'      => $formType,
            'sale_blueprint' => [
                'modo'      => 'auto',
                'servicios' => $itemsBlueprint,
            ],
            'form_data'           => $formData,
            'existing_venta_id'   => $venta->id,
            'existing_cliente_id' => $venta->cliente_id,
        ],
    ]);

    // 8) Enviar email con link
    try {

        \Mail::to($cliente->email_contacto)
            ->send(new \App\Mail\LeadConversionLinkMail($venta->lead, $link));

        // Registrar log
        \App\Models\LeadAutoEmailLog::create([
            'lead_id'             => $venta->lead_id,
            'estado'              => $venta->lead->estado->value ?? 'unknown',
            'intento'             => 1,
            'template_identifier' => 'conversion_link_manual_auto',
            'subject'             => 'Firma tu contrato y completa tus datos',
            'body_preview'        => 'Enlace manual auto venta #' . $venta->id,
            'scheduled_at'        => now(),
            'sent_at'             => now(),
            'status'              => 'sent',
            'mail_driver'         => config('mail.default'),
            'triggered_by_user_id'=> auth()->id(),
            'trigger_source'      => 'create_venta_after_create',
        ]);

        // Comentario sobre el lead
        $venta->lead->comentarios()->create([
            'user_id'   => 9999,
            'contenido' => "📤 Contrato enviado automáticamente al crear la venta (#{$venta->id}).",
        ]);

        Notification::make()
            ->title('Contrato enviado al cliente')
            ->success()
            ->body('El cliente ha recibido el enlace para completar datos, firmar y luego pagar.')
            ->send();

    } catch (\Exception $e) {

        Notification::make()
            ->title('Error al enviar el contrato')
            ->danger()
            ->body('No se pudo enviar el email. Revisa la configuración SMTP.')
            ->send();
    }
}





}