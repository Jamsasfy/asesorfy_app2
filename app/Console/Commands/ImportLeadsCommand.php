<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Enums\LeadEstadoEnum;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ImportLeadsCommand extends Command
{
    protected $signature = 'import:leads 
        {--file= : Ruta a un CSV concreto} 
        {--dir= : Ruta a una carpeta con varios CSV}';

    protected $description = 'Importa leads desde uno o varios CSV exportados de Vtiger';

    private const MAP = [
        'nombre_csv'   => 'Nombre',
        'apellido_csv' => 'Apellido',
        'email_csv'    => 'Correo electrónico principal',
        'telefono_csv' => 'Teléfono móvil',
        'creado_csv'   => 'Hora de creación',
    ];

    private const DB_COLS = [
        'nombre'     => 'nombre',
        'email'      => 'email',
        'telefono'   => 'tfn',
        'estado'     => 'estado',
        'created_at' => 'created_at',
    ];

    public function handle()
    {
        $files = $this->resolveFiles();
        if (empty($files)) {
            $this->error("No se encontraron archivos CSV. Usa --file=/ruta/archivo.csv o --dir=/ruta/carpeta");
            return 1;
        }

        $totalImportados = 0;
        $totalErrores = 0;
        $totalSaltados = 0;

        foreach ($files as $filePath) {
            $this->line(str_repeat('─', 80));
            $this->info("📄 Procesando: {$filePath}");

            if (!file_exists($filePath)) {
                $this->warn("   (No existe, saltando)");
                continue;
            }

            // === LECTURA + LIMPIEZA ===
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false || $fileContent === '') {
                $this->warn("   (Archivo vacío o no legible, saltando)");
                continue;
            }

            $encoding = mb_detect_encoding($fileContent, ['UTF-8','ISO-8859-1','latin1'], true) ?: 'ISO-8859-1';
            $utf8Content = mb_convert_encoding($fileContent, 'UTF-8', $encoding);

            $cleanContent = trim($utf8Content);

            // Si todo el archivo viene envuelto por comillas
            if (substr($cleanContent, 0, 1) === '"' && substr($cleanContent, -1) === '"') {
                $cleanContent = substr($cleanContent, 1, -1);
            }

            // Normalización de comillas dobles
            $cleanContent = str_replace('""', '"', $cleanContent);

            // ⚠️ No dependas de PHP_EOL -> divide por cualquier fin de línea (\r\n | \n | \r)
            $lines = preg_split("/\r\n|\n|\r/", $cleanContent);
            if (!$lines || count($lines) === 0) {
                $this->warn("   (No se pudieron detectar líneas en el CSV, saltando)");
                continue;
            }

            $header = str_getcsv(array_shift($lines));
            $headerCount = count($header);
            $dataRows = $lines;

            // Contadores por archivo
            $importados = 0;
            $errores = 0;
            $saltados = 0;

            $progressBar = $this->output->createProgressBar(count($dataRows));
            $progressBar->start();

            foreach ($dataRows as $index => $line) {
                if (empty(trim($line))) {
                    $saltados++;
                    $progressBar->advance();
                    continue;
                }

                $row = str_getcsv($line);
                if (count($row) !== $headerCount) {
                    Log::warning("[$filePath] Fila #" . ($index+1) . " corrupta. Cabeceras: $headerCount, fila: ".count($row));
                    $saltados++;
                    $progressBar->advance();
                    continue;
                }

                try {
                    $rowData = array_combine($header, $row);
                } catch (\Throwable $e) {
                    Log::error("[$filePath] Fila #" . ($index+1) . " array_combine(): " . $e->getMessage());
                    $errores++;
                    $progressBar->advance();
                    continue;
                }

                try {
                    // Validaciones mínimas
                    $email = trim((string)($rowData[self::MAP['email_csv']] ?? ''));
                    if ($email === '') {
                        Log::warning("[$filePath] Fila #" . ($index+1) . " saltada: email vacío.");
                        $saltados++;
                        $progressBar->advance();
                        continue;
                    }

                    $nombre = trim((string)($rowData[self::MAP['nombre_csv']] ?? ''));
                    $apellido = trim((string)($rowData[self::MAP['apellido_csv']] ?? ''));
                    $nombreCompleto = trim($nombre . ' ' . $apellido);

                    // Fecha robusta (si falla, usa ahora())
                    $fechaRaw = $rowData[self::MAP['creado_csv']] ?? null;
                    try {
                        $fechaCreacion = $fechaRaw ? Carbon::parse($fechaRaw) : now();
                    } catch (\Throwable $e) {
                        $fechaCreacion = now();
                    }

                    $leadData = [
                        self::DB_COLS['nombre']     => $nombreCompleto ?: null,
                        self::DB_COLS['email']      => $email,
                        self::DB_COLS['telefono']   => $rowData[self::MAP['telefono_csv']] ?? null,
                        self::DB_COLS['estado']     => LeadEstadoEnum::SIN_GESTIONAR,
                        self::DB_COLS['created_at'] => $fechaCreacion,
                    ];

                    Lead::updateOrCreate(
                        [ self::DB_COLS['email'] => $leadData[self::DB_COLS['email']] ],
                        $leadData
                    );

                    $importados++;
                } catch (\Throwable $e) {
                    Log::error("[$filePath] Error importando fila #" . ($index+1) . ": " . $e->getMessage());
                    Log::error("[$filePath] Datos fila: " . json_encode($rowData, JSON_UNESCAPED_UNICODE));
                    $errores++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->line(""); // salto

            // Resumen por archivo
            $this->info("✅ Archivo listo: $filePath");
            $this->info("   - Correctos: $importados");
            $this->error("  - Errores:   $errores");
            $this->warn("   - Saltados: $saltados");

            $totalImportados += $importados;
            $totalErrores    += $errores;
            $totalSaltados   += $saltados;
        }

        // Resumen global
        $this->line(str_repeat('═', 80));
        $this->info("🏁 Importación finalizada (todos los archivos)");
        $this->info("   - Correctos: $totalImportados");
        $this->error("  - Errores:   $totalErrores");
        $this->warn("   - Saltados: $totalSaltados");

        return 0;
    }

    /**
     * Resuelve los archivos a importar a partir de --file o --dir.
     * Si no se pasa nada, por defecto buscará en storage/app/import/*.csv
     */
    private function resolveFiles(): array
    {
        $fileOpt = $this->option('file');
        $dirOpt  = $this->option('dir');

        if ($fileOpt) {
            return [ $this->normalizePath($fileOpt) ];
        }

        $dir = $dirOpt
            ? $this->normalizePath($dirOpt)
            : storage_path('app/import');

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    private function normalizePath(string $path): string
    {
        // Permite pasar rutas relativas del estilo storage/app/import/xxx.csv
        if (!str_starts_with($path, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $path)) {
            // relativa -> desde base_path
            $absolute = base_path(trim($path, '/\\'));
            if (file_exists($absolute) || is_dir($absolute)) {
                return $absolute;
            }
        }
        return $path;
    }
}
