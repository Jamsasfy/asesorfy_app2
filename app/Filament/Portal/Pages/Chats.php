<?php

namespace App\Filament\Portal\Pages;

use App\Models\ChatConversacion;
use App\Models\Cliente;
use App\Models\TelegramLink;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Services\TelegramService;
use Filament\Notifications\Notification;

class Chats extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string|\UnitEnum|null $navigationGroup = 'Gestión';
    protected static ?string $navigationLabel = 'Chats y mi Asesor';

    protected string $view = 'filament.portal.pages.chats';

    public ?Cliente $cliente = null;
    public ?ChatConversacion $chat = null;

    public ?TelegramLink $telegramLink = null;
    public bool $isLinked = false;

    // ✅ NUEVO: Indica si el cliente tiene asesor asignado
    public bool $hasAsesor = false;

    // linked | pendiente | expirado | no_iniciado
    public string $telegramState = 'no_iniciado';

    public ?string $botUsername = null;

    // ✅ Estas 2 son las que tu blade pinta (linkUrl + expiresAt)
    public ?string $telegramLinkUrl = null;
    public ?string $telegramLinkExpiresAt = null;

        // Propiedades para el modal
    public bool $mostrarModalRevincular = false;
    public string $motivoRevincular = '';



    public function mount(): void
    {
       /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $clienteActivoId = session('cliente_activo_id');
        $this->cliente = $clienteActivoId
            ? $user?->clientes()->where('clientes.id', $clienteActivoId)->first()
            : $user?->clientes()->first();

        if (! $this->cliente) {
            return;
        }

        // ✅ NUEVO: Verificar que el cliente tiene asesor asignado
        if (! $this->cliente->asesor_id) {
            $this->hasAsesor = false;
            return; // No cargar nada más si no tiene asesor
        }

        $this->hasAsesor = true;

        // 1) Conversación (fuente de verdad de "vinculado")
        $this->chat = ChatConversacion::query()
            ->where('cliente_id', $this->cliente->id)
            ->latest('id')
            ->first();

        $this->isLinked = filled($this->chat?->telegram_chat_id);

        // 2) Último link (solo importa si NO está vinculado)
        $this->telegramLink = TelegramLink::query()
            ->where('cliente_id', $this->cliente->id)
            ->latest('id')
            ->first();

        $now = now();

        // ✅ FIX: Validación estricta de token válido
        $hasPendingValidLink =
            $this->telegramLink
            && blank($this->telegramLink->used_at)
            && $this->telegramLink->expires_at instanceof Carbon
            && $this->telegramLink->expires_at->isFuture();

        // ✅ FIX: Validación estricta de token expirado
        $isExpiredLink =
            $this->telegramLink
            && blank($this->telegramLink->used_at)
            && $this->telegramLink->expires_at instanceof Carbon
            && $this->telegramLink->expires_at->isPast();

        if ($this->isLinked) {
            $this->telegramState = 'linked';
        } elseif ($hasPendingValidLink) {
            $this->telegramState = 'pendiente';
        } elseif ($isExpiredLink) {
            $this->telegramState = 'expirado';
        } else {
            $this->telegramState = 'no_iniciado';
        }

        // 3) Username del bot (sin config nueva: probamos varias keys + env)
        $this->botUsername = $this->resolveBotUsername();

        // 4) Link interno por token (tu sistema actual)
        $this->telegramLinkUrl = null;
        $this->telegramLinkExpiresAt = null;

        if ($this->telegramLink) {
            $this->telegramLinkExpiresAt = $this->telegramLink->expires_at?->toIsoString();

            $token = $this->telegramLink->token;

            if (filled($token)) {
                if (Route::has('telegram.link')) {
                    $this->telegramLinkUrl = route('telegram.link', ['token' => $token]);
                } else {
                    $this->telegramLinkUrl = url("/telegram/link/{$token}");
                }
            }
        }
    }



public function abrirModalRevincular(): void
{
    $this->mostrarModalRevincular = true;
    $this->motivoRevincular = '';
}

public function cerrarModalRevincular(): void
{
    $this->mostrarModalRevincular = false;
    $this->motivoRevincular = '';
}

public function solicitarRevincular(): void
{
    if (!$this->cliente || !$this->hasAsesor) {
        return;
    }

    $this->validate([
        'motivoRevincular' => 'required|min:5|max:300',
    ], [
        'motivoRevincular.required' => 'Por favor indica el motivo.',
        'motivoRevincular.min'      => 'El motivo debe tener al menos 5 caracteres.',
        'motivoRevincular.max'      => 'El motivo no puede superar los 300 caracteres.',
    ]);

    $asesor = $this->cliente->asesor;
    if (!$asesor) {
        Notification::make()
            ->title('No se pudo enviar la solicitud')
            ->warning()
            ->send();
        $this->mostrarModalRevincular = false;
        return;
    }

    // Buscar o crear la conversación del cliente
    $chat = ChatConversacion::query()
        ->where('cliente_id', $this->cliente->id)
        ->latest('id')
        ->first();

    if ($chat) {
        // Insertar mensaje de sistema visible en el chat del asesor
        \App\Models\ChatMensaje::create([
            'chat_id'  => $chat->id,
            'origen'   => 'sistema',
            'tipo'     => 'text',
            'contenido' => "🔔 SOLICITUD DE REVINCULACIÓN DE TELEGRAM\n\nEl cliente solicita un nuevo enlace de vinculación.\n\nMotivo: {$this->motivoRevincular}\n\nPor favor, envíale un nuevo enlace desde el panel de administración.",
            'leido'    => false,
        ]);

        // Actualizar contadores para que aparezca como no leído
        $chat->increment('unread_count');
        $chat->update(['last_message_at' => now()]);
    }

    // Notificación en campana del asesor como respaldo
   \Filament\Notifications\Notification::make()
    ->title('🔔 Solicitud de revinculación')
    ->body("El cliente solicita un nuevo enlace de Telegram.")
    ->warning()
    ->actions([
\Filament\Actions\Action::make('ver_chat')
            ->label('Ver chat del cliente')
->url(\App\Filament\Resources\ClienteResource::getUrl('view', ['record' => $this->cliente->id], panel: 'admin'))
            ->markAsRead(),
    ])
    ->sendToDatabase($asesor);
    Notification::make()
        ->title('Solicitud enviada')
        ->body('Tu asesor ha sido notificado y te enviará el nuevo enlace pronto.')
        ->success()
        ->send();

    $this->mostrarModalRevincular = false;
    $this->motivoRevincular = '';
}


    /**
     * ✅ BOTÓN "Abrir Telegram" (APP)
     * - Si NO vinculado y hay token: tg://...&start=TOKEN
     * - Si vinculado: tg://resolve?domain=BOT
     * - Fallback: link interno (web) si falta bot username
     */
    public function getTelegramDeepLink(): ?string
    {
        // ✅ NUEVO: Si no tiene asesor, no devolver nada
        if (! $this->hasAsesor) {
            return null;
        }

        // Si NO vinculado -> preferimos "start=token"
        if (! $this->isLinked) {
            $token = $this->telegramLink?->token;

            // Si tengo bot username + token => deep link real a app
            if (filled($this->botUsername) && filled($token)) {
                $u = ltrim((string) $this->botUsername, '@');
                return "tg://resolve?domain={$u}&start={$token}";
            }

            // Fallback: que al menos haya botón y abra el link interno
            return $this->telegramLinkUrl;
        }

        // Vinculado -> abrir el bot/chat
        if (filled($this->botUsername)) {
            $u = ltrim((string) $this->botUsername, '@');
            return "tg://resolve?domain={$u}";
        }

        return null;
    }

    /**
     * ✅ BOTÓN "Abrir en navegador" (WEB)
     * - Si NO vinculado y hay token: https://t.me/BOT?start=TOKEN
     * - Si vinculado: https://t.me/BOT
     * - Fallback: link interno si falta bot username
     */
    public function getTelegramWebUrl(): ?string
    {
        // ✅ NUEVO: Si no tiene asesor, no devolver nada
        if (! $this->hasAsesor) {
            return null;
        }

        if (! $this->isLinked) {
            $token = $this->telegramLink?->token;

            if (filled($this->botUsername) && filled($token)) {
                $u = ltrim((string) $this->botUsername, '@');
                return "https://t.me/{$u}?start={$token}";
            }

            return $this->telegramLinkUrl;
        }

        if (filled($this->botUsername)) {
            $u = ltrim((string) $this->botUsername, '@');
            return "https://t.me/{$u}";
        }

        return null;
    }

    /**
     * Tu blade tiene wire:click="regenerateTelegramLink"
     * Esto genera uno nuevo (solo si NO está vinculado).
     */
    public function regenerateTelegramLink(): void
    {
        // ✅ NUEVO: Validar que tiene asesor antes de generar token
        if (! $this->cliente || $this->isLinked || ! $this->hasAsesor) {
            return;
        }

        // invalidar anteriores pendientes
        TelegramLink::query()
            ->where('cliente_id', $this->cliente->id)
            ->whereNull('used_at')
            ->update(['expires_at' => now()->subMinute()]);

        $link = TelegramLink::create([
            'cliente_id' => $this->cliente->id,
            'token'      => Str::random(48),
            'expires_at' => now()->addMinutes(30),
            'used_at'    => null,
        ]);

        $this->telegramLink = $link;

        // refrescar props que pinta el blade
        $this->telegramLinkExpiresAt = $link->expires_at?->toIsoString();

        if (Route::has('telegram.link')) {
            $this->telegramLinkUrl = route('telegram.link', ['token' => $link->token]);
        } else {
            $this->telegramLinkUrl = url("/telegram/link/{$link->token}");
        }

        $this->telegramState = 'pendiente';
    }

    protected function resolveBotUsername(): ?string
    {
        $keys = [
            'services.telegram.bot_username',
            'services.telegram.username',
            'telegram.bot_username',
            'telegram.username',
            'telegram.bots.default.username',
            'telegram.bots.bot.username',
            'botman.telegram.username',
        ];

        foreach ($keys as $key) {
            $val = config($key);
            if (filled($val)) {
                return (string) $val;
            }
        }

        $env = env('TELEGRAM_BOT_USERNAME');

        return filled($env) ? (string) $env : null;
    }
}