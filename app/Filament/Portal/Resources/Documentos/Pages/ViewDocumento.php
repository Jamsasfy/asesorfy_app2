<?php

namespace App\Filament\Portal\Resources\Documentos\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        $tab = request()->query('tab');

        $url = DocumentoResource::getUrl('index');
        if (filled($tab) && $tab !== 'todos') {
            $url .= '?tab=' . $tab;
        }

        return [
            Action::make('volver')
                ->label('Volver a documentos')
                ->icon('heroicon-o-arrow-left')
                ->color('primary')
                ->url($url),

            Action::make('subir_nuevo_documento')
    ->label('Subir nuevo documento')
    ->icon('heroicon-o-document-plus')
    ->color('warning')
    ->visible(function (): bool {
        $estado = $this->record->estado instanceof DocumentoEstadoEnum
            ? $this->record->estado
            : DocumentoEstadoEnum::tryFrom((string) $this->record->estado);

        return $estado === DocumentoEstadoEnum::RECHAZADO;
    })
    ->modalHeading('Subir documentos')
    ->closeModalByClickingAway(false)
    ->closeModalByEscaping(false)
    ->modalWidth('3xl')
    ->form([
        FileUpload::make('rutas')
    ->label('Archivos')
    ->disk('public')
    ->directory('documentos')
    ->maxSize(51200) // 50MB
    ->validationAttribute('Archivos')
    ->validationMessages([
        'uploaded' => 'El archivo no se pudo subir. Asegúrate de que no supera el tamaño máximo permitido.',
        'max'      => 'El archivo supera el tamaño máximo permitido (50 MB).',
    ])
    ->required()
    ->multiple()
    ->previewable(false) // ✅ fuera miniaturas/previews (compacto y seguro)
    ->maxFiles(20)       // ✅ evita modal infinito por cantidad
    ->helperText('Puedes subir varios a la vez (máx. 20 por tanda). Si tienes más, repite el proceso.')
    ->hint(function () {
        $maxKb = 51200; // tu maxSize() (KB)

        $toBytes = function (?string $val): int {
            $val = trim((string) $val);
            if ($val === '') return 0;
            $last = strtolower($val[strlen($val) - 1]);
            $num = (int) $val;

            return match ($last) {
                'g' => $num * 1024 * 1024 * 1024,
                'm' => $num * 1024 * 1024,
                'k' => $num * 1024,
                default => (int) $val,
            };
        };

        $phpUpload = $toBytes(ini_get('upload_max_filesize'));
        $phpPost   = $toBytes(ini_get('post_max_size'));

        $phpLimitBytes = 0;
        if ($phpUpload > 0 && $phpPost > 0) $phpLimitBytes = min($phpUpload, $phpPost);
        elseif ($phpUpload > 0) $phpLimitBytes = $phpUpload;
        elseif ($phpPost > 0) $phpLimitBytes = $phpPost;

        $filamentBytes = $maxKb * 1024;
        $effectiveBytes = $phpLimitBytes > 0 ? min($phpLimitBytes, $filamentBytes) : $filamentBytes;

        $effectiveMb = max(1, (int) floor($effectiveBytes / 1024 / 1024));

        return "Tamaño máximo por archivo: {$effectiveMb} MB";
    })
    ->uploadingMessage('Subiendo archivos… espera a que termine para enviar')
    ->acceptedFileTypes([
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])
    ->visibility('public')
    ->columnSpanFull(),


        \Filament\Forms\Components\Textarea::make('observaciones')
            ->label('Observaciones')
            ->helperText('Opcional. Si quieres, añade una nota para tu asesor (se copia en cada documento).')
            ->columnSpanFull(),
    ])
    ->action(function (array $data, $livewire): void {
        $user = auth()->user();

        $clienteId = (int) $this->record->cliente_id;
        $cliente = $clienteId ? \App\Models\Cliente::find($clienteId) : null;

        // ✅ Tipo/Subtipo por defecto (idéntico al headerAction)
        $categoria = \App\Models\DocumentoCategoria::query()
            ->where('nombre', 'Sin clasificar')
            ->first();

        $subtipo = $categoria
            ? \App\Models\DocumentoSubtipo::query()
                ->where('documento_categoria_id', $categoria->id)
                ->where('nombre', 'Pendiente de clasificar')
                ->first()
            : null;

        $rutas = (array) ($data['rutas'] ?? []);
        $observaciones = $data['observaciones'] ?? null;

        foreach ($rutas as $ruta) {
            $mime = filled($ruta) ? Storage::disk('public')->mimeType($ruta) : null;

            // ✅ Nombre automático por archivo (idéntico al headerAction)
            $random = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
            $extension = pathinfo((string) $ruta, PATHINFO_EXTENSION);
            $nombre = 'cliente_documento_' . $random . ($extension ? ".{$extension}" : '');

            $payload = [
                'cliente_id'             => $clienteId,
                'user_id'                => $user->id,
                'ruta'                   => $ruta,
                'mime_type'              => $mime,
                'observaciones'          => $observaciones,
                'observaciones_internas' => null,
                'estado'                 => \App\Enums\DocumentoEstadoEnum::PENDIENTE->value,
                'verificado'             => false,
                'nombre'                 => $nombre,
            ];

            if ($categoria && $subtipo) {
                $payload['tipo_documento_id'] = $categoria->id;
                $payload['subtipo_documento_id'] = $subtipo->id;
            }

            if ($cliente) {
                $payload['documentable_type'] = \App\Models\Cliente::class;
                $payload['documentable_id'] = $cliente->id;
            }

            \App\Models\Documento::create($payload);
        }

        \Filament\Notifications\Notification::make()
            ->title('Documentos subidos')
            ->success()
            ->send();

        // ✅ volver al listado respetando tab actual
        $tab = request()->query('tab');

        $url = \App\Filament\Portal\Resources\Documentos\DocumentoResource::getUrl('index');
        if (filled($tab) && $tab !== 'todos') {
            $url .= '?tab=' . $tab;
        }

        $livewire->redirect($url, navigate: true);
    }),

        ];
    }
}
