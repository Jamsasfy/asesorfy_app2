<x-filament-panels::page>
    @php
        $accent = '#41c0e9';

        /** @var array $paymentMethod */
        $data = is_array($paymentMethod ?? null) ? $paymentMethod : [];

        /** @var \App\Models\Cliente|null $clienteObj */
        $clienteObj = $cliente ?? null;

        $type   = $data['type'] ?? null; // card | sepa_debit | null
        $label  = $data['label'] ?? 'Sin método';
        $last4  = $data['last4'] ?? null;
        $brand  = $data['brand'] ?? null;

        $expM = $data['exp_month'] ?? null;
        $expY = $data['exp_year'] ?? null;
        $exp  = ($expM && $expY)
            ? str_pad((string) $expM, 2, '0', STR_PAD_LEFT) . '/' . substr((string) $expY, -2)
            : '—';

        $hasMethod = filled($type);

        $clienteNombre = $clienteObj?->razon_social
            ?? $clienteObj?->nombre
            ?? 'Tu cuenta';

        // Icono dinámico
        $brandIcon = match (strtolower((string) $brand)) {
            'visa', 'mastercard', 'amex' => 'heroicon-m-credit-card',
            default => ($type === 'sepa_debit' ? 'heroicon-m-building-library' : 'heroicon-m-credit-card'),
        };

        // Label bonito arriba derecha en "tarjeta"
        $brandLabel = $type === 'sepa_debit' ? 'SEPA' : strtoupper((string) ($brand ?: 'CARD'));

        // =========================
        // KPIs / CARDS "PRO" (mismo estilo que la suscripción)
        // =========================
        $kpiCardClass = 'group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
        $kpiBorder    = 'border border-sky-200/60 dark:border-sky-400/20';
        $kpiGlow      = 'before:content-[\'\'] before:absolute before:-inset-0.5 before:rounded-[1.25rem] before:bg-gradient-to-r before:from-sky-400/20 before:via-cyan-300/10 before:to-purple-400/10 before:opacity-0 hover:before:opacity-100 before:blur-xl before:transition';
        $kpiTopLine   = 'after:content-[\'\'] after:absolute after:left-5 after:right-5 after:top-0 after:h-[2px] after:rounded-full after:bg-gradient-to-r after:from-transparent after:via-sky-400/60 after:to-transparent after:opacity-70';

        // Variante más "AsesorFy" para Info útil
        $infoTopLine = 'after:content-[\'\'] after:absolute after:left-5 after:right-5 after:top-0 after:h-[2px] after:rounded-full after:bg-gradient-to-r after:from-transparent after:via-sky-500/70 after:to-transparent after:opacity-90';
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">

        {{-- COLUMNA IZQUIERDA --}}
        <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-6">

            {{-- HERO --}}
            <div class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                <div class="mb-8 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            Método de pago
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Este es el método actual usado para la facturación.
                        </p>
                    </div>

                    @if($hasMethod)
                        <div class="flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Configurado
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Pendiente
                        </div>
                    @endif
                </div>

                {{-- VISUAL PRINCIPAL: CARD o SEPA --}}
                <div class="relative max-w-md mx-auto lg:mx-0">

                    {{-- Glow sutil --}}
                    <div
                        class="pointer-events-none absolute -inset-1 rounded-[2rem] opacity-25 blur-xl"
                        style="background: radial-gradient(closest-side, {{ $accent }}33, transparent 70%);"
                    ></div>

                    @if($type === 'card')
                        {{-- TARJETA --}}
                        <div
                            class="relative flex h-56 flex-col justify-between rounded-2xl p-6 shadow-2xl transition-transform duration-500 hover:scale-[1.01]"
                            style="background: linear-gradient(135deg, #0f172a 0%, {{ $accent }} 120%); color: white;"
                        >
                            <div class="flex items-start justify-between opacity-95">
                                <div class="flex items-center gap-2">
                                    <x-filament::icon icon="{{ $brandIcon }}" class="h-6 w-6 text-white/80" />
                                    <div class="text-xs font-semibold text-white/75">
                                        Tarjeta
                                    </div>
                                </div>

                                <span class="text-lg font-black italic tracking-widest text-white/80">
                                    {{ $brandLabel }}
                                </span>
                            </div>

                            <div class="font-mono text-2xl tracking-[0.2em] text-white/90 drop-shadow-md">
                                @if($hasMethod && filled($last4))
                                    •••• •••• •••• {{ $last4 }}
                                @else
                                    SIN CONFIGURAR
                                @endif
                            </div>

                            <div class="flex items-end justify-between">
                                <div class="min-w-0">
                                    <div class="text-[10px] uppercase tracking-wider opacity-60">Titular</div>
                                    <div class="font-medium tracking-wide truncate max-w-[220px]">
                                        {{ $clienteNombre }}
                                    </div>
                                </div>

                                <div class="flex flex-col items-end">
                                    <div class="text-[10px] uppercase tracking-wider opacity-60">Expira</div>
                                    <div class="font-medium">{{ $exp }}</div>
                                </div>
                            </div>

                            <div
                                class="pointer-events-none absolute inset-0 rounded-2xl opacity-[0.08]"
                                style="background-image: radial-gradient(circle at 30% 30%, #ffffff 0, transparent 40%), radial-gradient(circle at 70% 70%, #ffffff 0, transparent 35%);"
                            ></div>
                        </div>

                    @elseif($type === 'sepa_debit')
                        {{-- SEPA (NO tarjeta) --}}
                        <div
                            class="relative overflow-hidden rounded-2xl p-6 shadow-2xl transition-transform duration-500 hover:scale-[1.01]"
                            style="background: linear-gradient(135deg, #0b1220 0%, #111827 55%, {{ $accent }}55 160%); color: white;"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/10">
                                        <x-filament::icon icon="heroicon-m-building-library" class="h-6 w-6 text-white/80" />
                                    </div>

                                    <div>
                                        <div class="text-sm font-bold">Adeudo directo SEPA</div>
                                        <div class="text-xs text-white/70">Cuenta bancaria</div>
                                    </div>
                                </div>

                                <span class="text-xs font-black uppercase tracking-[0.25em] text-white/70">
                                    SEPA
                                </span>
                            </div>

                            <div class="mt-6">
                                <div class="text-[10px] uppercase tracking-wider text-white/60">Cuenta</div>
                                <div class="mt-1 font-mono text-2xl tracking-[0.18em] text-white/90">
                                    @if(filled($last4))
                                        •••• {{ $last4 }}
                                    @else
                                        •••• ••••
                                    @endif
                                </div>
                                <div class="mt-2 text-xs text-white/65">
                                    Sin caducidad
                                </div>
                            </div>

                            <div class="mt-6 flex items-end justify-between">
                                <div class="min-w-0">
                                    <div class="text-[10px] uppercase tracking-wider opacity-60">Titular</div>
                                    <div class="font-medium tracking-wide truncate max-w-[260px]">
                                        {{ $clienteNombre }}
                                    </div>
                                </div>

                                <div class="text-xs text-white/70">
                                    Mandato gestionado por Stripe
                                </div>
                            </div>

                            <div
                                class="pointer-events-none absolute -right-16 -bottom-16 h-48 w-48 rounded-full opacity-20 blur-2xl"
                                style="background: radial-gradient(closest-side, {{ $accent }}, transparent 70%);"
                            ></div>
                        </div>

                    @else
                        {{-- SIN MÉTODO --}}
                        <div class="relative overflow-hidden rounded-2xl p-6 shadow-sm ring-1 ring-gray-950/10 dark:ring-white/10 bg-gray-50 dark:bg-white/5">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">
                                        Acción requerida
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        No hemos detectado un método de pago válido. Configúralo para evitar interrupciones.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Aviso si no hay método --}}
                @if(! $hasMethod)
                    <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                        <div class="flex gap-3">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div class="space-y-1">
                                <p class="font-bold">Necesitas configurar un método</p>
                                <p class="opacity-90">Haz clic en “Gestionar en Stripe” para añadir o cambiar tu forma de pago.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- INSIGHTS (mismo estilo pro) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                    <dt class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-finger-print" class="h-4 w-4" />
                        Stripe Customer ID
                    </dt>
                    <dd class="mt-2 font-mono text-sm font-bold text-gray-900 dark:text-white break-all">
                        {{ $clienteObj?->stripe_customer_id ?? 'No generado' }}
                    </dd>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Identificador interno en Stripe para tu cuenta.
                    </div>
                </div>

                <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                    <dt class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-shield-check" class="h-4 w-4" />
                        Seguridad
                    </dt>
                    <dd class="mt-2 text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Tokenizado (Stripe)
                    </dd>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        No almacenamos números completos: Stripe gestiona el dato sensible.
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA --}}
        <div class="lg:col-span-5 xl:col-span-4 space-y-6">

            <div class="rounded-3xl bg-gray-50 p-6 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <h3 class="font-bold text-gray-900 dark:text-white">Gestión en Stripe</h3>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Accede al portal seguro de Stripe para añadir o cambiar tu método de pago.
                </p>

                <div class="mt-6">
                    <x-filament::button
                        tag="a"
                        :href="$this->getBillingPortalUrl()"
                        target="_blank"
                        icon="heroicon-m-arrow-top-right-on-square"
                        icon-position="after"
                        size="lg"
                        class="w-full shadow-sm transition-transform active:scale-95"
                        style="background-color: {{ $accent }};"
                    >
                        {{ $hasMethod ? 'Gestionar método' : 'Añadir método' }}
                    </x-filament::button>
                </div>

                <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                    Te redirigiremos a stripe.com de forma segura
                </p>
            </div>

            {{-- INFO ÚTIL (mismo estilo pro + azul AsesorFy) --}}
            <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $infoTopLine }} {{ $kpiBorder }}">
                <h4 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-5 w-5" style="color: {{ $accent }};" />
                    <span style="color: {{ $accent }};">Info útil</span>
                </h4>

                <ul class="mt-4 space-y-4">
                    <li class="flex gap-3">
                        <div class="flex-none pt-1">
                            <div class="h-1.5 w-1.5 rounded-full bg-sky-400/70 dark:bg-sky-300/40"></div>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Privacidad</strong>
                            AsesorFy no guarda tu número completo. Solo los últimos dígitos para ayudarte a identificar el método.
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <div class="flex-none pt-1">
                            <div class="h-1.5 w-1.5 rounded-full bg-sky-400/70 dark:bg-sky-300/40"></div>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Varios métodos</strong>
                            En el portal de Stripe verás todos tus métodos (tarjeta / SEPA) y podrás cambiar el predeterminado.
                        </div>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</x-filament-panels::page>
