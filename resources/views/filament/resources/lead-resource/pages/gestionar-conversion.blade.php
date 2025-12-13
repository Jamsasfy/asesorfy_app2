<x-filament-panels::page>
    <div class="p-6 text-white">
        <h1 class="text-2xl font-bold">
            Gestionar Conversion – OK
        </h1>

        {{-- KPI --}}
        @include('filament.resources.leads.partials.kpi')

        {{-- Servicios --}}
        @include('filament.resources.leads.partials.servicios-table')
    </div>
</x-filament-panels::page>
