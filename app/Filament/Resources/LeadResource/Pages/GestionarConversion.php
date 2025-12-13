<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use Filament\Resources\Pages\Page;
use App\Models\Lead;
use App\Models\Servicio;
use App\Models\TipoCliente;
use Filament\Notifications\Notification;

class GestionarConversion extends Page
{
    protected static string $resource = LeadResource::class;

    // Apuntamos a tu archivo de vista principal
    protected static string $view = 'filament.resources.leads.partials.gestionar-conversion';

    // Propiedades públicas
    public $record;
    public $lead;
    public ?int $tipoClienteId = null;
    public array $items = [];

    public function mount($record): void
    {
        $this->lead = Lead::findOrFail($record);
        $this->tipoClienteId = $this->lead->cliente?->tipo_cliente_id ?? TipoCliente::first()?->id;
        $this->addItem(); // Fila inicial
    }

    // --- CÁLCULOS AUTOMÁTICOS ---
    public function getTotalesProperty(): array
    {
        $unico = 0;
        $recurrente = 0;

        foreach ($this->items as $item) {
            $c = (float)($item['cantidad'] ?? 0);
            $p = (float)($item['precio'] ?? 0);
            $d = (float)($item['descuento'] ?? 0);
            
            $subtotal = max(0, ($c * $p) - $d);

            if (($item['tipo'] ?? 'unico') === 'recurrente') {
                $recurrente += $subtotal;
            } else {
                $unico += $subtotal;
            }
        }

        $diasMes = now()->daysInMonth ?: 30;
        $diasRestantes = $diasMes - now()->day + 1;
        $prorrata = $recurrente > 0 ? ($recurrente / $diasMes) * $diasRestantes : 0;
        $totalHoy = ($unico + $prorrata) * 1.21; 

        return [
            'unico' => $unico,
            'recurrente' => $recurrente,
            'prorrata' => $prorrata,
            'dias_restantes' => $diasRestantes,
            'total_hoy' => $totalHoy,
        ];
    }

    // --- RELLENAR PRECIO AUTOMÁTICO ---
    public function updated($name, $value)
    {
        if (str_ends_with($name, '.servicio_id') && $value) {
            $parts = explode('.', $name);
            $index = $parts[1];

            $servicio = Servicio::find($value);
            if ($servicio) {
                $this->items[$index]['precio'] = $servicio->precio_base;
                $this->items[$index]['tipo'] = $servicio->tipo->value;
            }
        }
    }

    public function addItem(): void
    {
        $this->items[] = [
            'servicio_id' => null, 'precio' => 0, 'cantidad' => 1, 'descuento' => 0, 'tipo' => 'unico'
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function enviarPropuesta()
    {
        Notification::make()->title('Propuesta enviada')->success()->send();
    }
}