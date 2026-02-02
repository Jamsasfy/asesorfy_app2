<?php

namespace App\Console\Commands;

use App\Enums\DocumentoEstadoEnum;
use App\Jobs\NotificarPendientesRespuestaClienteJob;
use App\Models\Documento;
use Illuminate\Console\Command;

class RecordatorioPendientesRespuestaCliente extends Command
{
    protected $signature = 'asesorfy:recordatorio-pendientes-respuesta';
    protected $description = 'Dispara recordatorios (email + notificación) a clientes con documentos pendientes de respuesta (máx 1/día).';

    public function handle(): int
    {
        // Clientes que tienen al menos 1 doc pendiente de respuesta del cliente
        $clienteIds = Documento::query()
            ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
            ->whereNull('aclaracion_respondida_at')
            ->distinct()
            ->pluck('cliente_id')
            ->filter()
            ->values();

        $count = 0;

        foreach ($clienteIds as $clienteId) {
            NotificarPendientesRespuestaClienteJob::dispatch((int) $clienteId);
            $count++;
        }

        $this->info("Recordatorios encolados: {$count}");
        return self::SUCCESS;
    }
}

