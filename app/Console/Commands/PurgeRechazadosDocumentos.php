<?php

namespace App\Console\Commands;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Documento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeRechazadosDocumentos extends Command
{
    protected $signature = 'documentos:purge-rechazados {--days=30}';
    protected $description = 'Purga el archivo físico de documentos RECHAZADOS antiguos, manteniendo el registro en BBDD.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $query = Documento::query()
            ->where('estado', DocumentoEstadoEnum::RECHAZADO->value)
            ->whereNull('purged_at')        // ✅ no repurgar
            ->whereNotNull('ruta')
            ->where(function ($q) use ($cutoff) {
                // preferimos revisado_at, fallback updated_at
                $q->where('revisado_at', '<=', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('revisado_at')->where('updated_at', '<=', $cutoff);
                  });
            });

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nada que purgar.');
            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        $purged = 0;

        $query->chunkById(200, function ($docs) use ($disk, &$purged) {
            foreach ($docs as $doc) {
                $path = (string) $doc->ruta;

                if ($path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }

                // dejamos registro, pero quitamos fichero
                $doc->ruta = null;
                $doc->mime_type = null;

                $doc->purged_at = now();
                $doc->purged_by_id = null;      // sistema (evitamos depender del user 9999)
                $doc->purge_reason = 'auto_30d';

                $doc->save();

                $purged++;
            }
        });

        $this->info("Purgados: {$purged}");
        return self::SUCCESS;
    }
}
