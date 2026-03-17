<?php

namespace App\Filament\Portal\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Enums\FacturaEstadoEnum;
use App\Models\ChatConversacion;
use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use App\Models\Documento;
use App\Models\Factura;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    // ✅ Tipos exactos según Filament\Pages\Page en v4
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Inicio';
    protected static ?int $navigationSort = 1;
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected string $view = 'filament.portal.pages.dashboard';

    // Datos del cliente
    public ?Cliente $cliente = null;
    public ?ChatConversacion $chat = null;
    public Collection $suscripciones;
    
    // KPIs
    public int $documentosPendientes = 0;
    public int $facturasPendientes = 0;
    public ?string $proximoCobroFecha = null;
    public ?float $proximoCobroImporte = null;
    public ?string $ultimaConversacion = null;
    public bool $telegramVinculado = false;
    
    // Asesor
    public ?string $asesorNombre = null;
    public ?string $asesorEmail = null;
    public bool $tieneAsesor = false;

    public function mount(): void
    {
       /** @var \App\Models\User|null $user */
            $user = auth()->user();

            // ✅ Usar el cliente activo de sesión en lugar del primero
            $clienteActivoId = session('cliente_activo_id');
            
            $this->cliente = $clienteActivoId 
                ? $user?->clientes()->where('clientes.id', $clienteActivoId)->first()
                : $user?->clientes()->first();

            if (! $this->cliente) {
                return;
            }

        // ✅ ASESOR
        $asesor = $this->cliente->asesor;
        $this->tieneAsesor = (bool) $this->cliente->asesor_id;
        $this->asesorNombre = $asesor?->name ?? 'Sin asesor asignado';
        $this->asesorEmail = $asesor?->email;

        // ✅ KPI 1: Documentos pendientes de respuesta
        $this->documentosPendientes = Documento::query()
            ->where('cliente_id', $this->cliente->id)
            ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION)
            ->whereNull('aclaracion_respondida_at')
            ->count();

        // ✅ KPI 2: Facturas pendientes de pago
        $this->facturasPendientes = Factura::query()
            ->where('cliente_id', $this->cliente->id)
            ->where('estado', FacturaEstadoEnum::PENDIENTE_PAGO)
            ->count();

        // ✅ KPI 3: Próximo cobro
        $this->suscripciones = $this->cliente->suscripciones()
            ->where('estado', 'activa')
            ->with('servicio')
            ->get();

        $this->calcularProximoCobro();

        // ✅ KPI 4: Última conversación (Telegram)
        $this->chat = ChatConversacion::query()
            ->where('cliente_id', $this->cliente->id)
            ->latest('last_message_at')
            ->first();

        $this->telegramVinculado = (bool) $this->chat?->telegram_chat_id;
        
        if ($this->chat?->last_message_at && $this->telegramVinculado) {
            $this->ultimaConversacion = $this->chat->last_message_at
                ->timezone(config('app.timezone', 'Europe/Madrid'))
                ->diffForHumans();
        } else {
            $this->ultimaConversacion = null;
        }
    }

    protected function calcularProximoCobro(): void
    {
        $candidates = [];

        foreach ($this->suscripciones as $s) {
            $pf = $s->proxima_fecha_facturacion;
            if ($pf) {
                try {
                    $candidates[] = [
                        'fecha' => \Illuminate\Support\Carbon::parse($pf),
                        'importe' => (float) $s->precio_acordado,
                    ];
                } catch (\Throwable $e) {
                    // Ignorar fechas inválidas
                }
            }
        }

        if (! empty($candidates)) {
            // Ordenar por fecha más próxima
            usort($candidates, fn($a, $b) => $a['fecha'] <=> $b['fecha']);
            
            $proximo = $candidates[0];
            $this->proximoCobroFecha = $proximo['fecha']->format('d/m/Y');
            $this->proximoCobroImporte = $proximo['importe'];
        }
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Portal\Widgets\SelectorEmpresaWidget::class,
            \App\Filament\Portal\Widgets\NotificacionesWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 1;
    }

    public function marcarNotificacionCriticaLeida(int $notificacionId): void
    {
        $notificacion = \App\Models\NotificacionPortal::find($notificacionId);

        if ($notificacion) {
            $notificacion->marcarComoLeida(auth()->user());
        }
    }
}