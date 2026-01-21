<?php

namespace App\Filament\Portal\Resources\Documentos\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            Action::make('subir_correccion')
                ->label('Subir corrección')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible(function (): bool {
                    $estado = $this->record->estado instanceof DocumentoEstadoEnum
                        ? $this->record->estado
                        : DocumentoEstadoEnum::tryFrom((string) $this->record->estado);

                    return $estado === DocumentoEstadoEnum::RECHAZADO;
                })
                ->modalHeading('Subir documento corregido')
                ->modalWidth('2xl')
                ->form([
                    FileUpload::make('ruta')
                        ->label('Archivo corregido')
                        ->disk('public')
                        ->directory('documentos')
                        ->visibility('public')
                        ->maxSize(32768)
                        ->required()
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
                        ]),
                ])
                ->action(function (array $data): void {
                    $disk = Storage::disk('public');

                    $oldPath = (string) ($this->record->ruta ?? '');
                    $newPath = (string) ($data['ruta'] ?? '');

                    // ✅ Reemplaza el archivo en el MISMO documento (B1)
                    $this->record->ruta = $newPath;
                    $this->record->mime_type = $disk->mimeType($newPath);

                    // ✅ Reset de rechazo/revisión
                    $this->record->estado = DocumentoEstadoEnum::PENDIENTE;
                    $this->record->verificado = false;
                    $this->record->revisado_at = null;
                    $this->record->revisado_por_id = null;
                    $this->record->motivo_rechazo = null;

                    $this->record->save();

                    // ✅ Borra el fichero anterior del storage (si era distinto)
                    if (filled($oldPath) && $oldPath !== $newPath) {
                        try {
                            if ($disk->exists($oldPath)) {
                                $disk->delete($oldPath);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo borrar archivo anterior al subir corrección', [
                                'documento_id' => $this->record->id,
                                'old_path' => $oldPath,
                                'new_path' => $newPath,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $this->record->refresh();
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
