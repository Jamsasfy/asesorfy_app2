<?php

namespace App\Filament\Resources\VentaResource\Pages;

use Filament\Actions\DeleteAction;
use Exception;
use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion;
use App\Enums\ServicioTipoEnum;
use App\Services\FacturacionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\VentaCorreccionEstadoEnum; // <-- Importante añadir este
use App\Models\Venta;
use App\Services\CorreccionVentaService;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    // Redirección después de actualizar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Acciones de cabecera
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }



   // En EditVenta.php

protected function afterSave(): void
{
    $datosGuardados = $this->record->getAttributes();
    $datosOriginales = $this->record->getOriginal();

    if (
        isset($datosGuardados['correccion_estado']) &&
        $datosGuardados['correccion_estado'] === VentaCorreccionEstadoEnum::COMPLETADA->value &&
        ($datosOriginales['correccion_estado'] ?? null) !== VentaCorreccionEstadoEnum::COMPLETADA->value
    ) {
        try {
            // Refrescamos la venta para asegurarnos que los datos y relaciones están actualizados
            $this->record->refresh();

            // Ejecutamos el proceso completo de corrección (anula, abona, recrea venta y factura consolidada)
            CorreccionVentaService::procesar($this->record);

            Notification::make()
                ->title('¡Corrección Realizada con Éxito!')
                ->success()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title('¡Error al procesar la corrección!')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    } else {
        if ($this->record) {
            $this->record->updateTotal();

            // ✅ Solo una factura consolidada para servicios únicos
            FacturacionService::generarFacturaParaVenta($this->record);

            Notification::make()
                ->title("Factura generada para los servicios únicos.")
                ->success()
                ->send();
        }
    }
}




   /*  protected function procesarFacturacionUnicaParaVenta($venta): void
    {
        $suscripcionesUnicasAFacturar = ClienteSuscripcion::query()
            ->where('venta_origen_id', $venta->id)
            ->whereHas('servicio', fn($q) => $q->where('tipo', ServicioTipoEnum::UNICO))
            ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
            ->get();

        foreach ($suscripcionesUnicasAFacturar as $suscripcion) {
            $factura = FacturacionService::generarFacturaParaSuscripcionUnica($suscripcion);

            if ($factura) {
                Notification::make()
                    ->title("Factura {$factura->numero_factura} (Servicio Único) generada y marcada como PAGADA.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title("Error al generar factura para servicio único de suscripción ID {$suscripcion->id}.")
                    ->danger()
                    ->send();
            }
        }
    } */
}