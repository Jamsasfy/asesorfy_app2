<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TrabajadorResource\Pages\ListTrabajadors;
use App\Filament\Resources\TrabajadorResource\Pages\CreateTrabajador;
use App\Filament\Resources\TrabajadorResource\Pages\EditTrabajador;
use App\Filament\Resources\TrabajadorResource\Pages;
use App\Filament\Resources\TrabajadorResource\RelationManagers;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconSize;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Filament\Tables\Enums\RecordActionsPosition;


class TrabajadorResource extends Resource
{
    protected static ?string $model = Trabajador::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-f-city-worker';
    protected static string | \UnitEnum | null $navigationGroup = 'Usuarios plataforma';
    protected static ?string $navigationLabel = 'Trabajadores AsesorFy';
    protected static ?string $modelLabel = 'Trabajador AsesorFy';
    protected static ?string $pluralModelLabel = 'Trabajadores AsesorFy';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
    

public static function form(Schema $schema): Schema
{
    return $schema
        ->columns(1) // 🔒 Fuerza las sections en vertical
        ->components([

            // =====================================================
            // SECTION 1 · ACCESO A LA PLATAFORMA
            // =====================================================
            Section::make('Trabajador con acceso a AsesorFy')
                ->description('Datos de acceso a la plataforma y asignación de roles.')
                ->schema([
                    Group::make()
                        ->relationship('user')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('email')
                                ->label('Email de acceso')
                                ->email()
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('password')
                                ->label('Contraseña')
                                ->password()
                                ->revealable()
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->same('password_confirmation')
                                ->maxLength(191)
                                ->columnSpan(2),

                            TextInput::make('password_confirmation')
                                ->label('Confirmar contraseña')
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->helperText('Repite la contraseña de acceso.')
                                ->columnSpan(2),
                            
                            Toggle::make('acceso_app')
                                ->label('Acceso al panel de administración')
                                ->helperText('Activar para permitir acceso al panel admin.')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(4),
                            
                            // 👇 MOVER AQUÍ DENTRO
                            Select::make('roles')
                                ->label('Roles del trabajador')
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required()
                                ->native(false)
                                ->suffixIcon('heroicon-m-shield-check')
                                ->helperText('Asigna uno o varios roles.')
                                ->columnSpan(4),

                            // -------------------------------------------------
                            // CONFIGURACIÓN COMERCIAL (dentro del Group user)
                            // -------------------------------------------------
                            Section::make('Configuración de Comercial')
                                ->description('Período de prueba y datos de inicio como comercial.')
                                ->icon('heroicon-o-calendar-days')
                                ->schema([
                                    Forms\Components\DatePicker::make('fecha_inicio_comercial')
                                        ->label('Fecha de inicio como comercial')
                                        ->helperText('Día en que el trabajador comenzó como comercial.')
                                        ->nullable()
                                        ->displayFormat('d/m/Y')
                                        ->native(false),

                                    Forms\Components\Select::make('meses_prueba')
                                        ->label('Meses de período de prueba')
                                        ->options([
                                            1  => '1 mes',
                                            2  => '2 meses',
                                            3  => '3 meses',
                                            4  => '4 meses',
                                            5  => '5 meses',
                                            6  => '6 meses',
                                            9  => '9 meses',
                                            12 => '12 meses',
                                        ])
                                        ->default(3)
                                        ->native(false)
                                        ->helperText('Meses en los que no se aplican criterios de despido.'),

                                    Forms\Components\Placeholder::make('estado_prueba')
                                        ->label('Estado actual')
                                        ->content(function ($record) {
                                            // $record es el User dentro del Group
                                            if (!$record?->fecha_inicio_comercial) {
                                                return 'No configurado';
                                            }
                                            if ($record->estaEnPeriodoPrueba()) {
                                                $mesActual = $record->getMesActualPrueba();
                                                $fechaFin  = $record->fecha_inicio_comercial->copy()
                                                    ->addMonths($record->meses_prueba)
                                                    ->subDay();
                                                return new \Illuminate\Support\HtmlString(
                                                    "<span class='text-sm text-yellow-600 font-semibold'>⏳ EN PERÍODO DE PRUEBA (Mes {$mesActual}/{$record->meses_prueba}) — Fin: {$fechaFin->format('d/m/Y')}</span>"
                                                );
                                            }
                                            $fechaFin = $record->fecha_inicio_comercial->copy()
                                                ->addMonths($record->meses_prueba)
                                                ->subDay();
                                            return new \Illuminate\Support\HtmlString(
                                                "<span class='text-sm text-green-600 font-semibold'>✅ Período de prueba completado el {$fechaFin->format('d/m/Y')}</span>"
                                            );
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->visible(fn ($record) => $record?->hasRole('comercial'))
                                ->collapsible()
                                ->collapsed(true)
                                ->columnSpanFull(),

                            // -------------------------------------------------
                            // REGLAS DE COMISIÓN (dentro del Group user)
                            // -------------------------------------------------
                            Section::make('Reglas de Comisión')
                                ->description('Reglas de comisión asignadas a este comercial.')
                                ->icon('heroicon-o-calculator')
                                ->schema([
                                    Forms\Components\Repeater::make('asignacionesReglas')
                                        ->label('')
                                        ->relationship('asignacionesReglas')
                                        ->schema([
                                            Forms\Components\Select::make('regla_id')
                                                ->label('Regla')
                                                ->options(fn () => \App\Models\ComisionRegla::where('activa', true)
                                                    ->pluck('nombre', 'id')
                                                    ->toArray())
                                                ->required()
                                                ->live()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->columnSpan(2),

                                            Forms\Components\Toggle::make('es_obligatoria')
                                                ->label('Obligatoria')
                                                ->helperText('Si no alcanza esta regla, anula todas las comisiones del mes')
                                                ->default(false)
                                                ->inline(false),

                                            Forms\Components\Toggle::make('activa')
                                                ->label('Activa')
                                                ->default(true)
                                                ->inline(false),

                                            Forms\Components\Placeholder::make('resumen')
                                                ->label('Resumen de la regla')
                                                ->content(function (callable $get) {
                                                    $reglaId = $get('regla_id');
                                                    if (!$reglaId) {
                                                        return 'Selecciona una regla para ver el resumen';
                                                    }
                                                    $regla = \App\Models\ComisionRegla::find($reglaId);
                                                    if (!$regla) {
                                                        return '';
                                                    }
                                                    return new \Illuminate\Support\HtmlString(
                                                        view('filament.components.resumen-regla-comision', [
                                                            'regla' => $regla,
                                                        ])->render()
                                                    );
                                                })
                                                ->columnSpanFull()
                                                ->hidden(fn (callable $get) => !filled($get('regla_id'))),
                                        ])
                                        ->columns(4)
                                        ->defaultItems(0)
                                        ->addActionLabel('Añadir regla')
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string =>
                                            \App\Models\ComisionRegla::find($state['regla_id'] ?? null)?->nombre ?? 'Nueva regla'
                                        )
                                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                                        ->columnSpanFull(),
                                ])
                                ->visible(fn ($record) => $record?->hasRole('comercial'))
                                ->collapsible()
                                ->collapsed(true)
                                ->columnSpanFull(),
                        ])
                        ->columns(4),
                ])
                ->columnSpanFull(),

            // =====================================================
            // SECTION 2 · CONTRATOS DE INCENTIVOS (solo comerciales)
            // =====================================================
            Section::make('Contratos de Incentivos')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Placeholder::make('estado_contrato')
                        ->label('Estado del Contrato')
                        ->content(function ($record) {
                            if (!$record || !$record->user) {
                                return 'No disponible';
                            }

                            $user = $record->user;

                            $reglas = $user->asignacionesReglas()->where('activa', true)->count();
                            if ($reglas === 0) {
                                return '⚠️ Sin reglas asignadas';
                            }

                            $ultimoContrato = $user->contratosIncentivos()->latest('created_at')->first();

                            if (!$ultimoContrato) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-sm text-red-600 font-semibold">❌ Sin contrato generado</span>'
                                );
                            }

                            // Enviado pero no firmado
                            if ($ultimoContrato->estaPendiente()) {
                                $fechaEnvio = $ultimoContrato->fecha_envio->format('d/m/Y H:i');
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-sm text-yellow-600 font-semibold">📧 Enviado el ' . $fechaEnvio . ' — Pendiente de firma</span>'
                                );
                            }

                            // Tiene contrato firmado — comparar reglas actuales
                            $ultimoFirmado = $user->contratosIncentivos()
                                ->whereNotNull('fecha_firma')
                                ->latest('fecha_firma')
                                ->first();

                            $reglasActuales = $user->asignacionesReglas()
                                ->where('activa', true)
                                ->with('regla')
                                ->get()
                                ->map(fn ($a) => [
                                    'id'             => $a->regla_id,
                                    'nombre'         => $a->regla->nombre,
                                    'minimo'         => (string) $a->regla->minimo_mensual,
                                    'porcentaje'     => (string) $a->regla->porcentaje_comision,
                                    'penalizacion'   => $a->regla->penalizacion_baja_antes_meses,
                                    'es_obligatoria' => $a->es_obligatoria,
                                    'activa'         => true,
                                ])
                                ->sortBy('id')->values()->toArray();

                            $reglasContrato = collect($ultimoFirmado->reglas_snapshot)
                                ->map(fn ($r) => [
                                    'id'             => $r['id'],
                                    'nombre'         => $r['nombre'],
                                    'minimo'         => (string) $r['minimo'],
                                    'porcentaje'     => (string) $r['porcentaje'],
                                    'penalizacion'   => $r['penalizacion'],
                                    'es_obligatoria' => $r['es_obligatoria'],
                                    'activa'         => $r['activa'],
                                ])
                                ->sortBy('id')->values()->toArray();


                            if ($reglasActuales !== $reglasContrato) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-sm text-yellow-600 font-semibold">⚠️ Cambios en reglas — Anexo pendiente</span>'
                                );
                            }

                            $tipoLabel = $ultimoFirmado->tipo === 'base' ? 'Contrato Base' : 'Anexo';
                            return new \Illuminate\Support\HtmlString(
                                '<span class="text-sm text-green-600 font-semibold">✅ ' . $tipoLabel . ' firmado el ' . $ultimoFirmado->fecha_firma->format('d/m/Y') . '</span>'
                            );
                        }),

                    Forms\Components\Placeholder::make('contratos_list')
                        ->label('Contratos Firmados')
                        ->content(function ($record) {
                            if (!$record || !$record->user) {
                                return '';
                            }

                            $contratos = $record->user->contratosIncentivos()
                                ->whereNotNull('fecha_firma')
                                ->orderBy('fecha_firma', 'desc')
                                ->get();

                            if ($contratos->isEmpty()) {
                                return 'Ningún contrato firmado';
                            }

                            $html = '<ul style="margin: 0; padding-left: 20px;">';
                            foreach ($contratos as $contrato) {
                                $tipo  = $contrato->tipo === 'base' ? '📄 Contrato Base' : '📋 Anexo';
                                $fecha = $contrato->fecha_firma->format('d/m/Y');
                                $url   = $contrato->getPdfUrl();

                                if ($contrato->pdf_path) {
                                    $urlDescarga = route('descargar-contrato-firmado', ['id' => $contrato->id]);
                                    $html .= "<li>{$tipo} — Firmado el {$fecha} — <a href='{$urlDescarga}' target='_blank' style='color:#0ea5e9;'>Descargar PDF</a></li>";
                                } else {
                                    $html .= "<li>{$tipo} — Firmado el {$fecha}</li>";
                                }
                            }
                            $html .= '</ul>';

                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->visible(fn ($record) => $record && $record->user && $record->user->tieneContratoFirmado()),
                ])
                ->headerActions([
                    \Filament\Actions\Action::make('enviarContrato')
                        ->label(function ($record) {
                            if (!$record || !$record->user) {
                                return 'Enviar Contrato';
                            }

                            $user           = $record->user;
                            $ultimoContrato = $user->contratosIncentivos()->latest('created_at')->first();

                            if ($ultimoContrato && $ultimoContrato->estaPendiente()) {
                                return '📧 Reenviar Email de Firma';
                            }

                            if (!$user->tieneContratoFirmado()) {
                                return '⚠️ Enviar Contrato Base';
                            }

                            $ultimoFirmado = $user->contratosIncentivos()
                                ->whereNotNull('fecha_firma')
                                ->latest('fecha_firma')
                                ->first();

                            if ($ultimoFirmado) {
                                $reglasActuales = $user->asignacionesReglas()
                                    ->where('activa', true)
                                    ->with('regla')
                                    ->get()
                                    ->map(fn ($a) => [
                                        'id'             => $a->regla_id,
                                        'nombre'         => $a->regla->nombre,
                                        'minimo'         => (string) $a->regla->minimo_mensual,
                                        'porcentaje'     => (string) $a->regla->porcentaje_comision,
                                        'penalizacion'   => $a->regla->penalizacion_baja_antes_meses,
                                        'es_obligatoria' => $a->es_obligatoria,
                                        'activa'         => true,
                                    ])
                                    ->sortBy('id')->values()->toArray();

                                $reglasContrato = collect($ultimoFirmado->reglas_snapshot)
                                    ->map(fn ($r) => [
                                        'id'             => $r['id'],
                                        'nombre'         => $r['nombre'],
                                        'minimo'         => (string) $r['minimo'],
                                        'porcentaje'     => (string) $r['porcentaje'],
                                        'penalizacion'   => $r['penalizacion'],
                                        'es_obligatoria' => $r['es_obligatoria'],
                                        'activa'         => $r['activa'],
                                    ])
                                    ->sortBy('id')->values()->toArray();

                                if ($reglasActuales !== $reglasContrato) {
                                    return '⚠️ Enviar Anexo';
                                }
                            }

                            return '📧 Reenviar Copia Firmada';
                        })
                        ->icon('heroicon-o-paper-airplane')
                        ->color(function ($record) {
                            if (!$record || !$record->user) {
                                return 'gray';
                            }

                            $user           = $record->user;
                            $ultimoContrato = $user->contratosIncentivos()->latest('created_at')->first();

                            if ($ultimoContrato && $ultimoContrato->estaPendiente()) {
                                return 'warning';
                            }

                            if (!$user->tieneContratoFirmado()) {
                                return 'danger';
                            }

                            $ultimoFirmado = $user->contratosIncentivos()
                                ->whereNotNull('fecha_firma')
                                ->latest('fecha_firma')
                                ->first();

                            if ($ultimoFirmado) {
                                $reglasActuales = $user->asignacionesReglas()
                                    ->where('activa', true)
                                    ->with('regla')
                                    ->get()
                                    ->map(fn ($a) => [
                                        'id'             => $a->regla_id,
                                        'nombre'         => $a->regla->nombre,
                                        'minimo'         => (string) $a->regla->minimo_mensual,
                                        'porcentaje'     => (string) $a->regla->porcentaje_comision,
                                        'penalizacion'   => $a->regla->penalizacion_baja_antes_meses,
                                        'es_obligatoria' => $a->es_obligatoria,
                                        'activa'         => true,
                                    ])
                                    ->sortBy('id')->values()->toArray();

                                $reglasContrato = collect($ultimoFirmado->reglas_snapshot)
                                    ->map(fn ($r) => [
                                        'id'             => $r['id'],
                                        'nombre'         => $r['nombre'],
                                        'minimo'         => (string) $r['minimo'],
                                        'porcentaje'     => (string) $r['porcentaje'],
                                        'penalizacion'   => $r['penalizacion'],
                                        'es_obligatoria' => $r['es_obligatoria'],
                                        'activa'         => $r['activa'],
                                    ])
                                    ->sortBy('id')->values()->toArray();

                                if ($reglasActuales !== $reglasContrato) {
                                    return 'danger';
                                }
                            }

                            return 'success';
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Enviar Contrato de Incentivos')
                        ->modalDescription(function ($record) {
                            if (!$record || !$record->user) {
                                return '';
                            }

                            $user            = $record->user;
                            $reglasAsignadas = $user->asignacionesReglas()
                                ->where('activa', true)
                                ->with('regla')
                                ->get();

                            if ($reglasAsignadas->isEmpty()) {
                                return 'El comercial no tiene reglas asignadas.';
                            }

                            $ultimoContrato = $user->contratosIncentivos()->latest('created_at')->first();

                            if ($ultimoContrato && $ultimoContrato->estaPendiente()) {
                                $descripcion = "Se reenviará el email de firma al comercial.<br><br>";
                            } elseif (!$user->tieneContratoFirmado()) {
                                $descripcion = "Se generará el contrato BASE y se enviará por email al comercial para su firma.<br><br>";
                            } else {
                                $ultimoFirmado = $user->contratosIncentivos()
                                    ->whereNotNull('fecha_firma')
                                    ->latest('fecha_firma')
                                    ->first();

                                $hayDiferencias = false;
                                if ($ultimoFirmado) {
                                    $reglasActuales = $user->asignacionesReglas()
                                        ->where('activa', true)
                                        ->with('regla')
                                        ->get()
                                        ->map(fn ($a) => [
                                            'id'             => $a->regla_id,
                                            'nombre'         => $a->regla->nombre,
                                            'minimo'         => (string) $a->regla->minimo_mensual,
                                            'porcentaje'     => (string) $a->regla->porcentaje_comision,
                                            'penalizacion'   => $a->regla->penalizacion_baja_antes_meses,
                                            'es_obligatoria' => $a->es_obligatoria,
                                            'activa'         => true,
                                        ])
                                        ->sortBy('id')->values()->toArray();

                                    $reglasContrato = collect($ultimoFirmado->reglas_snapshot)
                                        ->map(fn ($r) => [
                                            'id'             => $r['id'],
                                            'nombre'         => $r['nombre'],
                                            'minimo'         => (string) $r['minimo'],
                                            'porcentaje'     => (string) $r['porcentaje'],
                                            'penalizacion'   => $r['penalizacion'],
                                            'es_obligatoria' => $r['es_obligatoria'],
                                            'activa'         => $r['activa'],
                                        ])
                                        ->sortBy('id')->values()->toArray();

                                    $hayDiferencias = $reglasActuales !== $reglasContrato;
                                }

                                $descripcion = $hayDiferencias
                                    ? "Se generará un ANEXO al contrato base y se enviará por email al comercial para su firma.<br><br>"
                                    : "Se reenviará una copia del contrato firmado al email del comercial.<br><br>";
                            }

                            $descripcion .= "<strong>Reglas incluidas:</strong><br><ul style='margin:8px 0;padding-left:20px;'>";
                            foreach ($reglasAsignadas as $asignacion) {
                                $obligatoria = $asignacion->es_obligatoria
                                    ? ' <span style="color:#dc2626;font-size:11px;font-weight:bold;">(OBLIGATORIA)</span>'
                                    : '';
                                $minimo     = '€' . number_format($asignacion->regla->minimo_mensual, 2, ',', '.');
                                $porcentaje = $asignacion->regla->porcentaje_comision . '%';
                                $descripcion .= "<li><strong>{$asignacion->regla->nombre}</strong>{$obligatoria}<br>";
                                $descripcion .= "<span style='font-size:12px;color:#666;'>Mínimo: {$minimo} | Comisión: {$porcentaje}</span></li>";
                            }
                            $descripcion .= "</ul>";

                            return new \Illuminate\Support\HtmlString($descripcion);
                        })
                        ->action(function ($record) {
                            if (!$record || !$record->user) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Usuario no encontrado')
                                    ->send();
                                return;
                            }

                            $user   = $record->user;
                            $reglas = $user->asignacionesReglas()->where('activa', true)->count();

                            if ($reglas === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sin reglas asignadas')
                                    ->body('El comercial debe tener al menos una regla activa asignada.')
                                    ->send();
                                return;
                            }

                            try {
                                // Si el contrato está vigente (sin cambios), no hacer nada
                                $ultimoFirmado = $user->contratosIncentivos()
                                    ->whereNotNull('fecha_firma')
                                    ->latest('fecha_firma')
                                    ->first();

                                if ($ultimoFirmado) {
                                    $reglasActuales = $user->asignacionesReglas()
                                        ->where('activa', true)
                                        ->with('regla')
                                        ->get()
                                        ->map(fn ($a) => [
                                            'id'             => $a->regla_id,
                                            'nombre'         => $a->regla->nombre,
                                            'minimo'         => (string) $a->regla->minimo_mensual,
                                            'porcentaje'     => (string) $a->regla->porcentaje_comision,
                                            'penalizacion'   => $a->regla->penalizacion_baja_antes_meses,
                                            'es_obligatoria' => $a->es_obligatoria,
                                            'activa'         => true,
                                        ])
                                        ->sortBy('id')->values()->toArray();

                                    $reglasContrato = collect($ultimoFirmado->reglas_snapshot)
                                        ->map(fn ($r) => [
                                            'id'             => $r['id'],
                                            'nombre'         => $r['nombre'],
                                            'minimo'         => (string) $r['minimo'],
                                            'porcentaje'     => (string) $r['porcentaje'],
                                            'penalizacion'   => $r['penalizacion'],
                                            'es_obligatoria' => $r['es_obligatoria'],
                                            'activa'         => $r['activa'],
                                        ])
                                        ->sortBy('id')->values()->toArray();

                                    if ($reglasActuales === $reglasContrato) {
                                        // Reenviar copia de TODOS los contratos firmados
                                        try {
                                            \Illuminate\Support\Facades\Mail::to($user->email)
                                                ->send(new \App\Mail\ContratoIncentivosComercialFirmadoMail($ultimoFirmado));

                                            \Filament\Notifications\Notification::make()
                                                ->success()
                                                ->title('Copia reenviada')
                                                ->body('Se ha enviado una copia del contrato firmado al comercial.')
                                                ->send();
                                        } catch (\Exception $e) {
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('Error')
                                                ->body('No se pudo enviar el email: ' . $e->getMessage())
                                                ->send();
                                        }
                                        return;
                                    }
                                }

                                $contratoPendiente = $user->contratosIncentivos()
                                    ->whereNull('fecha_firma')
                                    ->latest('created_at')
                                    ->first();

                                if ($contratoPendiente) {
                                    \Illuminate\Support\Facades\Mail::to($user->email)
                                        ->send(new \App\Mail\ContratoIncentivosMail($contratoPendiente));

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Email reenviado')
                                        ->body('Se ha reenviado el email de firma al comercial.')
                                        ->send();

                                    return;
                                }

                                $service  = new \App\Services\ContratoIncentivosService();
                                $tipo     = $user->tieneContratoFirmado() ? 'anexo' : 'base';
                                $contrato = $service->generarContrato($user, $tipo);

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Contrato generado')
                                    ->body("Contrato {$tipo} generado y enviado por email.")
                                    ->send();

                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error al generar contrato')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        })
                        ->visible(function ($record) {
                            if (!$record || !$record->user || !$record->user->hasRole('comercial')) {
                                return false;
                            }
                            return $record->user->asignacionesReglas()->where('activa', true)->count() > 0;
                        }),
                ])
                ->visible(fn ($record) => $record?->user?->hasRole('comercial'))
                ->collapsible()
                ->collapsed(true)
                ->columnSpanFull(),

            // =====================================================
            // SECTION 3 · DATOS DEL TRABAJADOR
            // =====================================================
            Section::make('Datos del trabajador')
                ->description('Demás datos relativos al trabajador a nivel laboral y de accesos a la plataforma')
                ->schema([

                    Select::make('oficina_id')
                        ->relationship('oficina', 'nombre')
                        ->preload()
                        ->searchable()
                        ->required(),

                    TextInput::make('apellidos')
                        ->maxLength(191),

                    TextInput::make('telefono')
                        ->tel()
                        ->required()
                        ->rule('regex:/^[0-9]{9}$/')
                        ->helperText('Debe tener 9 dígitos'),

                    TextInput::make('dni_o_cif')
                        ->label('DNI o CIF')
                        ->required()
                        ->maxLength(191),

                    TextInput::make('cargo')
                        ->maxLength(191),

                    Textarea::make('direccion')
                        ->columnSpanFull(),

                    Textarea::make('observaciones')
                        ->columnSpanFull(),

                    TextInput::make('email_personal')
                        ->email()
                        ->required()
                        ->maxLength(191),

                    TextInput::make('numero_seg_social')
                        ->label('Número Seguridad Social')
                        ->rule('digits:12')
                        ->maxLength(191),

                    TextInput::make('numero_cuenta_nomina')
                        ->label('Número cuenta nómina')
                        ->maxLength(191),

                    Select::make('departamento_id')
                        ->label('Departamento')
                        ->relationship('departamento', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Selecciona un departamento'),

                ])
                ->columns(4)
                ->columnSpanFull(),

        ]);
}



    public static function table(Table $table): Table
    {
        return $table      
                ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
 
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nombre')                    
                    ->sortable(),
                TextColumn::make('apellidos')
                    ->searchable(),
                TextColumn::make('rol_estado')
                    ->label('Rol')
                    ->badge()
                    ->getStateUsing(fn ($record) =>
                        $record->user && $record->user->roles->isNotEmpty()
                            ? implode(', ', $record->user->roles->pluck('name')->toArray())
                            : '⚠️ Sin rol, asignar uno'
                    )
                    ->color(fn ($state) => str_contains($state, 'Sin rol') ? 'warning' : 'primary'),
               TextColumn::make('departamento.nombre')
                        ->label('Departamento')
                        ->badge()
                        ->color('info')
                        ->placeholder('Sin departamento')
                        ->searchable()
                        ->sortable(),        
                TextColumn::make('oficina.nombre')                  
                    ->sortable(),               
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('dni_o_cif')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email trabajo')
                    ->searchable(),
             /*    TextColumn::make('numero_seg_social')
                    ->searchable(),
                TextColumn::make('numero_cuenta_nomina')
                    ->searchable(), */
                IconColumn::make('user.acceso_app')
                    ->label('Acceso app')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),    
                TextColumn::make('created_at')
                ->label('Fecha de alta')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user.name')
                ->label('Nombre')
                ->relationship('user', 'name')
                ->searchable(),
                Filter::make('apellidos')
                    ->schema([
                        TextInput::make('valor')
                            ->label('Apellido')
                            ->placeholder('Buscar apellido'),
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['valor']) return $query;

                        return $query->where('apellidos', 'like', "%{$data['valor']}%");
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return $data['valor']
                            ? 'Apellido: ' . $data['valor']
                            : null;
                    }),
               SelectFilter::make('oficina.nombre')
                ->label('Oficina')
                ->relationship('oficina', 'nombre')
                ->preload()
                ->searchable(),
              SelectFilter::make('departamento') // Filtramos por la relación
                ->relationship('departamento', 'nombre')
                ->label('Filtrar por Departamento'),
           
                Filter::make('rol')
                //->label('Rol del usuario')
                ->schema([
                    Select::make('rol_id')
                        ->label('Rol del trabajador')
                        ->options(Role::query()->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                ])
                ->query(function ($query, array $data) {
                    if (! $data['rol_id']) return $query;
            
                    return $query->whereHas('user.roles', function ($q) use ($data) {
                        $q->where('id', $data['rol_id']);
                    });
                }),
                Filter::make('acceso_app')
                    ->label('Acceso')
                    ->schema([
                        Select::make('estado')
                            ->label('Estado de acceso a la app')
                            ->options([
                                '1' => 'Con acceso',
                                '0' => 'Sin acceso',
                            ])
                            ->placeholder('Todos'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['estado'])) return $query;

                        return $query->whereHas('user', function ($q) use ($data) {
                            $q->where('acceso_app', $data['estado']);
                        });
                    }),
               
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
           ->recordActions([
                EditAction::make()
                    ->label(''),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrabajadors::route('/'),
            'create' => CreateTrabajador::route('/create'),
            'edit' => EditTrabajador::route('/{record}/edit'),
        ];
    }
}
