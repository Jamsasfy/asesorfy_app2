<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\VentaResource;
use App\Models\Cliente;
use App\Models\Lead;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;




class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;




 public ?string $leadId = null; // Variable para "recordar" el ID del Lead

    /**
     * Al cargar la página, capturamos el lead_id y comprobamos si ya existe el cliente.
     */
    public function mount(): void
{
    // Guardamos el lead_id de la URL en nuestra variable.
    $this->leadId = request()->query('lead_id');

    // Si no nos llega un lead_id, detenemos la creación.
    if (!$this->leadId) {
        Notification::make()
            ->title('Acceso no permitido')
            ->body('Solo se pueden crear clientes a partir de un Lead convertido.')
            ->danger()
            ->send();

        $this->redirect(LeadResource::getUrl('index'));
        return;
    }

    // ▼▼▼ LÍNEA CORREGIDA ▼▼▼
    // Buscamos el Lead y, a través de su relación, vemos si ya tiene un cliente.
    $existing = Lead::find($this->leadId)?->cliente;

    if ($existing) {
        Notification::make()
            ->body('Este lead ya tiene un cliente. Creando la venta directamente.')
            ->info()->send();

        // Si ya existe, saltamos directamente a crear la venta.
        $this->redirect(
            VentaResource::getUrl('create', [
                'cliente_id' => $existing->getKey(),
                'lead_id'    => $this->leadId,
            ])
        );
        return;
    }

    // Si no existe, continuamos con la carga normal del formulario.
    parent::mount();
}

    /**
     * Después de guardar el nuevo cliente, lo vinculamos con su Lead de origen.
     */
    protected function afterCreate(): void
    {
        if ($this->leadId) {
            Lead::where('id', $this->leadId)
                ->update(['cliente_id' => $this->record->id]);
        }
    }

    /**
     * Una vez creado el cliente, SIEMPRE redirigimos a crear su primera venta.
     */
    protected function getRedirectUrl(): string
    {
        return VentaResource::getUrl('create', [
            'cliente_id' => $this->record->id,
            'lead_id'    => $this->leadId, // Usamos la variable que guardamos en mount()
        ]);
    }

    // El resto de tus métodos (handleRecordCreation, getCreatedNotification) se quedan como están.
    // ... (pega aquí tus métodos handleRecordCreation y getCreatedNotification sin cambios)
    protected function handleRecordCreation(array $data): Model
    {
        if (empty($data['razon_social']) && (empty($data['nombre']) || empty($data['apellidos']))) {
            Notification::make()
                ->title('❌ Falta información')
                ->body('Rellena razón social o nombre+apellidos.')
                ->danger()->persistent()->send();
            $this->halt();
            return new ($this->getModel())();
        }
        if (empty($data['razon_social'])) {
            $data['razon_social'] = trim("{$data['nombre']} {$data['apellidos']}");
        }
        return parent::handleRecordCreation($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('✅ Cliente creado correctamente')
            ->body('Ahora, por favor, crea su primera venta.')
            ->success()
            ->icon('icon-customer')
            ->persistent();
    }























/*      protected function afterCreate(): void
    {
       // parent::afterCreate();

        $leadId = request()->query('lead_id');
        if ($leadId) {
            Lead::where('id', $leadId)
                ->update(['cliente_id' => $this->record->getKey()]);
        }
    } 



    public function mount(): void
    {
        // Si venimos con lead_id y ya existe Cliente, saltamos directamente a Venta
        $leadId = request()->query('lead_id');
        if ($leadId) {
            $existing = Cliente::where('lead_id', $leadId)->first();
            if ($existing) {
                // Notificamos de forma opcional
                Notification::make()
                    ->body('Este lead ya se convirtió a cliente. Vamos a crear la venta.')
                    ->info()
                    ->send();

                // Redirigimos al formulario de Venta
                $this->redirect(
                    VentaResource::getUrl('create', [
                        'cliente_id' => $existing->getKey(),
                        'lead_id'    => $leadId,
                    ])
                );

                return; // Termina el mount para no renderizar el formulario
            }
        }

        parent::mount();
    }


     protected array $leadParams = [];


     protected function getRedirectUrl(): string
{
    $state  = $this->form->getState();
    $leadId = $state['lead_id'] ?? null;
    $next   = $state['next']    ?? null;

    // Caso A: si ya existía cliente vinculado al lead, vamos a crear venta
    if ($leadId && $existing = \App\Models\Cliente::where('lead_id', $leadId)->first()) {
        return \App\Filament\Resources\VentaResource::getUrl('create', [
            'cliente_id' => $existing->getKey(),
            'lead_id'    => $leadId,
        ]);
    }

    // Caso B: primera creación, si next es 'sale' o 'venta'
    if (in_array($next, ['sale', 'venta'], true) && $leadId) {
        return \App\Filament\Resources\VentaResource::getUrl('create', [
            'cliente_id' => $this->record->getKey(),
            'lead_id'    => $leadId,
        ]);
    }

    // Caso C: vista normal de Cliente
    return static::getResource()::getUrl('view', [
        'record' => $this->record->getKey(),
    ]);
}


    protected function handleRecordCreation(array $data): Model
    {
        // Tu validación y autocompletado de razon_social…
        if (
            empty($data['razon_social']) &&
            (empty($data['nombre']) || empty($data['apellidos']))
        ) {
            Notification::make()
                ->title('❌ Falta información')
                ->body('Rellena razón social o nombre+apellidos.')
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
            return new $this->getModel();
        }
        if (empty($data['razon_social'])) {
            $data['razon_social'] = trim("{$data['nombre']} {$data['apellidos']}");
        }
        return parent::handleRecordCreation($data);
    }


    protected function getCreatedNotification(): ?Notification
{
    return Notification::make()
        ->title('✅ Cliente creado correctamente')
        ->body('El cliente ha sido añadido al sistema. Recuerda revisar su documentación y estado.')
        ->success() // puedes cambiar por ->info(), ->warning(), ->danger()
        ->icon('icon-customer') // cualquier icono Heroicon
        ->persistent(); // no se cierra solo
} */


}
