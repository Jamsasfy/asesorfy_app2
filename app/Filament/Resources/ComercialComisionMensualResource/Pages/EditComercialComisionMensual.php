<?php

namespace App\Filament\Resources\ComercialComisionMensualResource\Pages;

use App\Filament\Resources\ComercialComisionMensualResource;
use App\Models\ComisionMensual;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditComercialComisionMensual extends EditRecord
{
    protected static string $resource = ComercialComisionMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar bonos desde las comisiones mensuales
        $primeraComision = ComisionMensual::where('comercial_id', $this->record->comercial_id)
            ->where('año', $this->record->año)
            ->where('mes', $this->record->mes)
            ->whereNotNull('bonos')
            ->first();

        if ($primeraComision && !empty($primeraComision->bonos)) {
            $data['bonos_datos'] = $primeraComision->bonos;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['bonos_datos'])) {
            $bonos      = [];
            $totalBonos = 0;

            foreach ($data['bonos_datos'] as $bono) {
                $bonos[] = [
                    'importe'       => $bono['importe'],
                    'descripcion'   => $bono['descripcion'],
                    'creado_por_id' => $bono['creado_por_id'] ?? Auth::id(),
                    'creado_at'     => $bono['creado_at'] ?? now()->toDateTimeString(),
                ];
                $totalBonos += (float) $bono['importe'];
            }

            // Actualizar bonos en todas las comisiones mensuales del mes
            ComisionMensual::where('comercial_id', $this->record->comercial_id)
                ->where('año', $this->record->año)
                ->where('mes', $this->record->mes)
                ->update([
                    'bonos'      => json_encode($bonos),
                    'total_bonos' => $totalBonos,
                    'importe_final' => DB::raw("importe_comision_calculado + {$totalBonos}"),
                ]);

            $data['total_bonos'] = $totalBonos;
            $data['total_final'] = ($this->record->total_comisiones_calculado ?? 0) + $totalBonos;

            unset($data['bonos_datos']);
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();

        if ($this->record->estado === 'borrador' && Auth::user()->hasRole(['super_admin', 'coordinador'])) {
            $actions[] = \Filament\Actions\Action::make('aprobar')
                ->label('Guardar y Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar comisión')
                ->modalDescription('Se guardarán los cambios y se enviará email al comercial.')
                ->disabled(fn () => !$this->record->comercial?->puedeAprobarComisiones())
                ->tooltip(fn () => !$this->record->comercial?->puedeAprobarComisiones()
                    ? 'Contrato de incentivos pendiente de firma'
                    : null)
                ->action(function () {
                    // Verificar contrato firmado y vigente
                    if (!$this->record->comercial?->puedeAprobarComisiones()) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede aprobar')
                            ->body('El comercial debe firmar el contrato de incentivos actualizado antes de poder aprobar comisiones.')
                            ->persistent()
                            ->send();
                        return;
                    }

                    $this->save();

                    DB::beginTransaction();
                    try {
                        $this->record->update(['estado' => 'aprobada']);

                        ComisionMensual::where('comercial_id', $this->record->comercial_id)
                            ->where('año', $this->record->año)
                            ->where('mes', $this->record->mes)
                            ->update([
                                'estado'          => 'aprobada',
                                'aprobada_por_id' => Auth::id(),
                                'aprobada_at'     => now(),
                            ]);

                        $comercial = $this->record->comercial;
                        $mesNombre = ucfirst(\Carbon\Carbon::create($this->record->año, $this->record->mes, 1)->locale('es')->monthName);

                        ComercialComisionMensualResource::enviarEmailAprobacion(
                            $comercial,
                            $this->record->año,
                            $this->record->mes,
                            $mesNombre,
                            $this->record->fresh()
                        );

                        DB::commit();

                        Notification::make()
                            ->success()
                            ->title('Comisión aprobada')
                            ->body("Email enviado a {$comercial->email}")
                            ->send();

                        return redirect(static::getResource()::getUrl('index'));

                    } catch (\Exception $e) {
                        DB::rollBack();

                        Notification::make()
                            ->danger()
                            ->title('Error al aprobar')
                            ->body($e->getMessage())
                            ->send();

                        Log::error('Error aprobando comisión', [
                            'historial_id' => $this->record->id,
                            'error'        => $e->getMessage(),
                        ]);
                    }
                });
        }

        return $actions;
    }
}
