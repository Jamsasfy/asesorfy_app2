<?php

namespace App\Filament\Pages;

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use App\Enums\ClienteEstadoEnum;
use App\Filament\Resources\ClienteResource;
use Filament\Pages\Page;
use App\Models\Cliente; // Asegúrate de importar tu modelo Cliente
use App\Models\User; // Importamos el modelo User para obtener el usuario autenticado
use Filament\Tables; // Necesario para HasTable y sus componentes
use BezhanSalleh\FilamentShield\Traits\HasPageShield; // El trait de Filament Shield
use Illuminate\Database\Eloquent\Builder; // Necesario para el tipo de retorno de getTableQuery
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\AsesorTotalClientesWidget; // Importa la clase del widget
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class MisClientesAsignados extends Page implements HasTable // Para poder usar una tabla
{
    use InteractsWithTable; // Funcionalidad para la tabla
    use HasPageShield; // Para la protección de la página con Shield

    // Conservamos el icono que te generó si te gusta, o puedes cambiarlo
    protected static string | \BackedEnum | null $navigationIcon = 'icon-cliente-asignado'; // Cambié a 'user-group', pero usa el que prefieras
    protected ?string $subheading = 'Clientes que tienes asignados como asesor' ; // Subtítulo opcional para la página

 protected static string | \UnitEnum | null $navigationGroup = 'Mi espacio de trabajo';
    // Propiedades que definimos para la página
    protected static ?string $navigationLabel = 'Mis Clientes Asignados'; // Nombre en el menú
    protected static ?string $title = 'Mis Clientes Asignados'; // Título que se muestra en la página
    protected static ?string $slug = 'mis-clientes-asignados'; // URL: tu-dominio.com/admin/mis-clientes-asignados

    // Vista Blade que se usará para esta página (ya lo tenías)
    protected string $view = 'filament.pages.mis-clientes-asignados';

    public static function getNavigationBadge(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();
            $count = Cliente::where('asesor_id', $user->id)->count();
            return $count > 0 ? (string) $count : null; // Muestra el conteo si es mayor que 0
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    //livewire:widget

    // Propiedades públicas para pasar datos a la vista Blade
    public int $totalMisClientes = 0;
    public int $misClientesActivos = 0;
    public int $misClientesAtencionImpago = 0; // <-- NUEVA PROPIEDAD
    public string $nombreAsesor = '';
    public int $documentosPendientesPlaceholder = 0; // Para el placeholder de documentos

    public function mount(): void
    {
        /** @var User $asesor */
        $asesor = Auth::user();

        if ($asesor) {
            $this->nombreAsesor = $asesor->name;

            $queryBaseAsesor = Cliente::where('asesor_id', $asesor->id);

            $this->totalMisClientes = (clone $queryBaseAsesor)->count();
            $this->misClientesActivos = (clone $queryBaseAsesor)->where('estado', 'activo')->count();
            // CALCULAR LA NUEVA ESTADÍSTICA
            $this->misClientesAtencionImpago = (clone $queryBaseAsesor)
                                              ->whereIn('estado', ['requiere_atencion', 'impagado'])
                                              ->count();
            $this->documentosPendientesPlaceholder = 0; // Mantenemos el placeholder
        }
    }

     // 1. DEFINIR LA CONSULTA PARA LA TABLA
    protected function getTableQuery(): Builder // El nombre correcto del método es getTableQuery
    {
        /** @var User $user */
        $user = Auth::user(); // Obtenemos el usuario autenticado
        return Cliente::query()->where('asesor_id', $user->id)->orderBy('created_at', 'desc');
    }

    // 2. DEFINIR LAS COLUMNAS DE LA TABLA
    // Por ahora, una columna simple para que no dé error. Luego añadiremos más.
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('razon_social')
                ->label('Razón Social')
                ->searchable(isIndividual: true)
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ?: '-'),

            TextColumn::make('dni_cif')
                ->label('DNI o CIF')
                ->searchable(isIndividual: true)
                ->sortable(),
              

             TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('apellidos')
                ->label('Apellidos')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),


            TextColumn::make('tipoCliente.nombre')
                ->label('Tipo')
                ->badge()
                ->sortable(),

            TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    // CAMBIO 1: La función ahora espera un objeto ClienteEstadoEnum
                    ->color(fn (ClienteEstadoEnum $state): string => match ($state) { 
                        // CAMBIO 2: Comparamos con los casos del Enum
                        ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                        ClienteEstadoEnum::ACTIVO => 'success',
                        ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                        ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                        ClienteEstadoEnum::BLOQUEADO, ClienteEstadoEnum::BAJA => 'gray',
                        default => 'gray',
                    })
                ->sortable(),

            TextColumn::make('provincia')
                ->label('Provincia')
                ->sortable(),

            TextColumn::make('localidad')
            ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),

            TextColumn::make('telefono_contacto')
                ->label('Teléfono')
                ->searchable(isIndividual: true),

            TextColumn::make('email_contacto')
                ->label('Email')
                ->searchable(isIndividual: true),

                TextColumn::make('asesor.name')
                ->label('Asesor')
                ->badge()
                ->getStateUsing(fn ($record) =>
                    $record->asesor
                        ? $record->asesor->name
                        : '⚠️ Sin asignar'
                )
                ->color(fn ($state) => str_contains($state, 'Sin asignar') ? 'warning' : 'success'),

            TextColumn::make('created_at')
                ->label('Creado en App')
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),

            TextColumn::make('fecha_alta')
                ->label('Fecha de Alta')
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),    
               
            TextColumn::make('fecha_baja')
                ->label('Fecha de Baja servicio')
                ->dateTime('d/m/y - H:m')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),        
           
           
        ];
    }


    // NUEVO MÉTODO PARA LOS FILTROS
    protected function getTableFilters(): array
    {
        return [
             SelectFilter::make('estado')
                ->label('Estado')
                ->preload()
                ->options([
                    'pendiente' => 'Pendiente',
                    'activo' => 'Activo',
                    'impagado' => 'Impagado',
                    'bloqueado' => 'Bloqueado',
                    'rescindido' => 'Rescindido',
                    'baja' => 'Baja',
                    'requiere_atencion' => 'Requiere atención',
                ])
                
                ->searchable(),

            SelectFilter::make('tipo_cliente_id')
                ->label('Tipo de cliente')
                ->relationship('tipoCliente', 'nombre')
                ->preload()
                ->searchable(),

            SelectFilter::make('provincia')
                ->label('Provincia')
                ->options(array_keys(config('provincias.provincias')))
                ->searchable(),
               
            DateRangeFilter::make('fecha_alta')
                ->label('Alta APP')
                ->placeholder('Rango de fechas a buscar'),  
            DateRangeFilter::make('fecha_baja')
                ->label('Baja APP')
                ->placeholder('Rango de fechas a buscar'),      
           
                ];
    }

protected function getTableFiltersLayout(): ?FiltersLayout
{
    return FiltersLayout::AboveContent;
}
   protected function getTableActions(): array
    {
        return [
            ViewAction::make()
                ->url(fn (Cliente $record): string => ClienteResource::getUrl('view', ['record' => $record]))
                ->openUrlInNewTab(),
            EditAction::make()
                ->url(fn (Cliente $record): string => ClienteResource::getUrl('edit', ['record' => $record]))
                ->openUrlInNewTab(),
            // No incluimos DeleteAction aquí si la política/permisos de ClienteResource lo manejan
        ];
    }
 
      protected function getHeaderWidgets(): array
        {
            return [
                AsesorTotalClientesWidget::class
            ];
        }   
}