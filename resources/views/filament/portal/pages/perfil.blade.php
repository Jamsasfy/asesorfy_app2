{{-- resources/views/filament/portal/pages/perfil.blade.php --}}
<x-filament-panels::page>
    @php
        $accent = '#41c0e9';
        $user = auth()->user();
        
        $cardClass = 'rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
    @endphp

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full opacity-20 blur-3xl" style="background: {{ $accent }}"></div>

        <div class="relative">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl text-2xl font-black text-white" style="background: {{ $accent }};">
                        {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white">
                            {{ $user->name }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $user->email }}
                        </p>
                    </div>
                </div>

                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-400/20">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Cuenta activa
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- COLUMNA IZQUIERDA: Datos personales --}}
        <div class="{{ $cardClass }}">
            <div class="flex items-center gap-2 mb-6">
                <x-filament::icon icon="heroicon-m-identification" class="h-5 w-5" style="color: {{ $accent }};" />
                <h2 class="text-base font-black text-gray-900 dark:text-white">
                    Información personal
                </h2>
            </div>

            <div class="space-y-4">
                {{-- Nombre --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Nombre completo
                    </label>
                    <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-gray-700">
                        {{ $user->name }}
                    </div>
                </div>

                {{-- Email --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Email
                    </label>
                    <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-gray-700">
                        {{ $user->email }}
                    </div>
                </div>

                {{-- Fecha de registro --}}
                @if($user->cuenta_activada_at)
                <div>
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Cuenta activada
                    </label>
                    <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-gray-700">
                        {{ $user->cuenta_activada_at->format('d/m/Y H:i') }}
                    </div>
                </div>
                @endif

                {{-- Info --}}
                <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-xs text-sky-900 dark:border-sky-800/50 dark:bg-sky-900/20 dark:text-sky-200">
                    <div class="flex gap-3">
                        <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 shrink-0" />
                        <p>
                            Para modificar tus datos personales, contacta con tu asesor.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA: Cambiar contraseña --}}
        <div class="{{ $cardClass }}">
            <div class="flex items-center gap-2 mb-6">
                <x-filament::icon icon="heroicon-m-lock-closed" class="h-5 w-5" style="color: {{ $accent }};" />
                <h2 class="text-base font-black text-gray-900 dark:text-white">
                    Seguridad y acceso
                </h2>
            </div>

            <form wire:submit="updatePassword" class="space-y-4">
                {{-- Contraseña actual --}}
                <div>
                    <label for="current_password" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Contraseña actual
                    </label>
                    <input 
                        type="password" 
                        id="current_password"
                        wire:model="passwordData.current_password"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400"
                        required
                    >
                    @error('passwordData.current_password')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nueva contraseña --}}
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Nueva contraseña
                    </label>
                    <input 
                        type="password" 
                        id="password"
                        wire:model="passwordData.password"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400"
                        required
                        minlength="8"
                    >
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Mínimo 8 caracteres</p>
                    @error('passwordData.password')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Confirmar contraseña --}}
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Confirmar nueva contraseña
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation"
                        wire:model="passwordData.password_confirmation"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400"
                        required
                        minlength="8"
                    >
                    @error('passwordData.password_confirmation')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Botón submit --}}
                <div class="pt-2">
                    <button 
                        type="submit"
                        class="w-full flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-bold text-white shadow-sm transition-all hover:opacity-90 active:scale-[0.98]"
                        style="background-color: {{ $accent }};"
                    >
                        <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5" />
                        Actualizar contraseña
                    </button>
                </div>
            </form>

            {{-- Cerrar sesión --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button
                    wire:click="logout"
                    wire:confirm="¿Estás seguro de que quieres cerrar sesión?"
                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60"
                >
                    <x-filament::icon icon="heroicon-m-arrow-right-on-rectangle" class="h-5 w-5" />
                    Cerrar sesión
                </button>
            </div>
        </div>
    </div>

    {{-- Sección adicional: Empresas vinculadas --}}
    @php
        $clientes = $user->clientes ?? collect();
    @endphp

    @if($clientes->count() > 0)
    <div class="{{ $cardClass }} mt-6">
        <div class="flex items-center gap-2 mb-6">
            <x-filament::icon icon="heroicon-m-building-office-2" class="h-5 w-5" style="color: {{ $accent }};" />
            <h2 class="text-base font-black text-gray-900 dark:text-white">
                Empresas vinculadas a las que tienes acceso
            </h2>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            @foreach($clientes as $c)
                <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold text-white shrink-0" style="background: {{ $accent }};">
                            {{ strtoupper(substr($c->razon_social ?? $c->nombre ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-black text-gray-900 dark:text-white truncate">
                                {{ $c->razon_social ?? $c->nombre . ' ' . $c->apellidos }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $c->dni_cif }}
                            </div>
                            @if($c->id === session('cliente_activo_id'))
                                <div class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Activa ahora
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</x-filament-panels::page>