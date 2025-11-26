<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion; // <-- Asegúrate de que esta línea esté
use App\Enums\ServicioTipoEnum;     // <-- Asegúrate de que esta línea esté
use App\Services\FacturacionService; // <-- Asegúrate de que esta línea esté
use Filament\Notifications\Notification; // <-- Asegúrate de que esta línea esté
use Filament\Resources\Pages\CreateRecord;

// Modelos y Enums que ya tenías o necesitas importar:
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Models\Proyecto;
use App\Models\Servicio;
use App\Models\Venta; // Necesario para type-hinting si lo usas en closures
use Filament\Actions;
use Filament\Notifications\Actions\Action as NotifyAction; // Si usas este alias


class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    // Redirección después de crear (ya lo tenías)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Este método se ejecuta automáticamente DESPUÉS de que la Venta se ha creado en la base de datos.
     */
    protected function afterCreate(): void
{
    if ($this->record) {
        // 1. Crear proyectos y suscripciones desde la venta
        $this->record->processSaleAfterCreation();

        // 2. Actualizar el total de la venta (precio, descuentos, etc.)
        $this->record->updateTotal();

        // 3. Generar la factura consolidada para servicios únicos
        $factura = \App\Services\FacturacionService::generarFacturaParaVenta($this->record);

        if ($factura && $factura->items()->count() > 0) {
            Notification::make()
                ->title("Factura {$factura->numero_factura} generada correctamente")
                ->success()
                ->send();
        }

        // 4. Notificar si se han creado proyectos asociados
        if ($this->record->proyectos()->exists()) {
            Notification::make()
                ->warning()
                ->title('Proyectos y Suscripciones Creadas')
                ->body('Se han generado proyectos de activación. Algunas suscripciones pueden estar pendientes hasta que se completen.')
                ->send();
        }

        // 5. Actualizar el estado del lead si hay uno vinculado
       /*  if ($this->record->lead_id && $this->record->lead) {
            $this->record->lead->update([
                'estado' => \App\Enums\LeadEstadoEnum::CONVERTIDO,
            ]);
        } */
    }
}



    /**
     * Método auxiliar para procesar las suscripciones únicas asociadas a una Venta.
     * Busca las suscripciones de tipo UNICO y ACTIVA y genera una factura PAGADA para cada una.
     * Este método es llamado después de crear o actualizar una Venta.
     */
   /*  protected function procesarFacturacionUnicaParaVenta($venta): void
    {
        // Buscamos todas las ClienteSuscripcion que:
        // a) Están vinculadas a esta Venta (usando 'venta_origen_id').
        // b) Su Servicio asociado es de tipo UNICO.
        // c) Su estado es ACTIVA (indicando que aún no han sido facturadas y finalizadas).
        $suscripcionesUnicasAFacturar = ClienteSuscripcion::query()
            ->where('venta_origen_id', $venta->id)
            ->whereHas('servicio', fn($q) => $q->where('tipo', ServicioTipoEnum::UNICO))
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA) 
            ->get();

        foreach ($suscripcionesUnicasAFacturar as $suscripcion) {
            // Llamamos a nuestro servicio para generar la factura única.
            $factura = FacturacionService::generarFacturaParaSuscripcionUnica($suscripcion);

            if ($factura) {
                // Si la factura se generó con éxito, mostramos una notificación verde.
                Notification::make()
                    ->title("Factura {$factura->numero_factura} (Servicio Único) generada y marcada como PAGADA.")
                    ->success()
                    ->send();
            } else {
                // Si hubo un error, mostramos una notificación roja.
                Notification::make()
                    ->title("Error al generar factura para servicio único de suscripción ID {$suscripcion->id}.")
                    ->danger()
                    ->send();
            }
        }
    } */
}