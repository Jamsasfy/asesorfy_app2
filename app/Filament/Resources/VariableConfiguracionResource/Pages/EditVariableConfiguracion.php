<?php

namespace App\Filament\Resources\VariableConfiguracionResource\Pages;

use Illuminate\Contracts\Encryption\DecryptException;
use App\Filament\Resources\VariableConfiguracionResource;
use App\Models\VariableConfiguracion;
use App\Services\ConfiguracionService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model; // <--- ¡Importa el Model de Eloquent!

class EditVariableConfiguracion extends EditRecord
{
    protected static string $resource = VariableConfiguracionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['es_secreto']) && $data['es_secreto']) {
            $data['valor_variable'] = '';
        }
        return $data;
    }

    // FIRMA CORREGIDA: Ahora acepta $record y devuelve Illuminate\Database\Eloquent\Model
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // $record ya es la instancia del modelo VariableConfiguracion que estamos editando.
        // Ahora es $record en lugar de $originalRecord
        $originalRecord = $record;

        $valorParaGuardar = (string) $data['valor_variable'];

        if ($data['es_secreto']) {
            if (!empty($valorParaGuardar)) {
                $valorParaGuardar = Crypt::encryptString($valorParaGuardar);
            } else {
                $valorParaGuardar = $originalRecord->valor_variable;
            }
        } else {
            if (str_starts_with($valorParaGuardar, 'eyJpdiI')) {
                try {
                    $valorParaGuardar = Crypt::decryptString($valorParaGuardar);
                } catch (DecryptException $e) {
                    Log::warning("Intento de descifrar un valor no válido para {$data['nombre_variable']}: " . $e->getMessage());
                }
            }
        }

        // Actualizamos el modelo $record (que es la instancia que Filament espera que devolvamos)
        $record->nombre_variable = $data['nombre_variable'];
        $record->valor_variable = $valorParaGuardar;
        $record->tipo_dato = $data['tipo_dato'];
        $record->descripcion = $data['descripcion'];
        $record->es_secreto = $data['es_secreto'];
        $record->save(); // Guarda los cambios en la base de datos

        return $record; // Devolvemos la instancia del modelo actualizada
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}