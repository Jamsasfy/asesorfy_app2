@php
    $emailDestino = $emailDestino ?? '—';
@endphp

<div class="space-y-4" x-data="{ pasteWarn: false }">
    <div class="relative overflow-hidden rounded-2xl border border-sky-200/70 bg-white p-5 shadow-sm
                dark:border-sky-900/40 dark:bg-gray-950/35">
        <div class="absolute -right-28 -top-28 h-64 w-64 rounded-full bg-sky-500/10 blur-2xl dark:bg-sky-400/10"></div>

        <div class="relative">
            <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700/70 dark:text-sky-300/70">
                Confirmación de email
            </div>

            <div class="mt-1 text-lg font-black text-gray-900 dark:text-white">
                Revisa el destinatario
            </div>

            <div class="mt-3 rounded-xl border border-sky-200/70 bg-sky-50/60 px-4 py-3
                        dark:border-sky-900/50 dark:bg-sky-950/20">
                <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700/70 dark:text-sky-300/70">
                    Se enviará a
                </div>

                <div class="mt-1 text-sm font-extrabold text-gray-900 dark:text-white">
                    <span class="text-base font-extrabold normal-case tracking-normal">
                        {{ $emailDestino }}
                    </span>
                </div>
            </div>

            <div class="mt-4">
                <div class="mb-1 text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                    Escribe el email para confirmar
                </div>

                <input
                    type="email"
                    wire:model.defer="emailConfirm"
                    x-on:paste.prevent="
                        pasteWarn = true;
                        setTimeout(() => pasteWarn = false, 2500);
                    "
                    x-on:keydown.ctrl.v.prevent="
                        pasteWarn = true;
                        setTimeout(() => pasteWarn = false, 2500);
                    "
                    x-on:keydown.meta.v.prevent="
                        pasteWarn = true;
                        setTimeout(() => pasteWarn = false, 2500);
                    "
                    class="w-full rounded-xl border border-sky-300/60 bg-white px-4 py-3 text-sm font-semibold text-gray-900
                           focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                           dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-gray-100"
                    placeholder="Vuelve a escribir el email..."
                />

                {{-- Aviso anti-pegar --}}
                <div
                    x-cloak
                    x-show="pasteWarn"
                    x-transition.opacity.duration.200ms
                    class="mt-2 text-xs font-bold text-amber-700 dark:text-amber-200"
                >
                    te pillamos¡¡¡¡ 😱 no se puede pegar, escribelo para confirmar :)
                </div>

                @error('emailConfirm')
                    <div class="mt-2 text-xs font-bold text-red-600 dark:text-red-400">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="mt-4 text-xs text-gray-600 dark:text-gray-300">
                Esto evita envíos a un email incorrecto cuando el cliente cambió de correo por teléfono.
            </div>
        </div>
    </div>
</div>
