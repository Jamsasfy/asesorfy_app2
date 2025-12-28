<?php

namespace App\Services;

use Exception;
use App\Models\VariableConfiguracion;
use Illuminate\Support\Facades\Cache; // Para la caché
use Illuminate\Support\Facades\Crypt; // Para el cifrado
use Illuminate\Support\Facades\Log;   // Para registrar errores

class ConfiguracionService
{
    /**
     * Obtiene el valor de una variable de configuración usando la caché.
     */
    public static function get(string $nombreVariable, mixed $default = null): mixed
    {
        // 1. Carga todas las variables desde la caché para no consultar la base de datos.
        $configuraciones = Cache::rememberForever('app_configuraciones', function () {
            return VariableConfiguracion::all()->keyBy('nombre_variable');
        });

        $variable = $configuraciones->get($nombreVariable);

        if (!$variable) {
            return $default;
        }

        $valor = $variable->valor_variable;

        // 2. Descifra el valor si es un secreto
        if ($variable->es_secreto) {
            try {
                $valor = Crypt::decryptString($valor);
            } catch (Exception $e) {
                Log::error("Error al descifrar la variable '{$nombreVariable}': " . $e->getMessage());
                return $default;
            }
        }

        // 3. Devuelve el valor con el tipo correcto
        return match ($variable->tipo_dato) {
            'numero_entero'  => (int) $valor,
            'numero_decimal' => (float) $valor,
            'booleano'       => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            default          => (string) $valor,
        };
    }

    /**
     * Guarda o actualiza una variable y limpia la caché.
     */
    public static function set(
        string $nombreVariable,
        mixed $valor,
        string $tipoDato,
        ?string $descripcion = null,
        bool $esSecreto = false
    ): VariableConfiguracion {
        
        $valorParaGuardar = (string) $valor;

        if ($esSecreto) {
            $valorParaGuardar = Crypt::encryptString($valorParaGuardar);
        }

        $variable = VariableConfiguracion::updateOrCreate(
            ['nombre_variable' => $nombreVariable],
            [
                'valor_variable' => $valorParaGuardar,
                'tipo_dato'      => $tipoDato,
                'descripcion'    => $descripcion,
                'es_secreto'     => $esSecreto,
            ]
        );

        // Limpia la caché para que el próximo 'get' lea el valor actualizado
        Cache::forget('app_configuraciones');

        return $variable;
    }
}