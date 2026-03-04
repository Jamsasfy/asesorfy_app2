<?php

namespace App\Filament\Pages;

use App\Jobs\SendTelegramMessageJob;
use App\Mail\TelegramVinculacionMail;
use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Models\Cliente;
use App\Models\TelegramLink;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
//use Filament\Support\Enums\MaxWidth;


class MisChats extends Page
{
    use WithFileUploads, HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'icon-telegram';
    protected static ?string $navigationLabel = 'Mis chats';
    protected static ?string $title = 'Mis chats';
    protected static string|\UnitEnum|null $navigationGroup = 'Mi espacio de trabajo';

    protected string $view = 'filament.pages.mis-chats';

    public ?int $selectedChatId = null;
    public string $search = '';
    public string $message = '';
    public bool $sending = false;

    public int $messagesTake = 80;
    public int $messagesStep = 60;
    public int $messagesTakeMax = 800;

    public int $chatsTake = 10;
    public int $chatsStep = 10;
    public int $chatsTakeMax = 400;

    /**
     * INPUT temporal (lo que selecciona el usuario en el file input).
     * OJO: con multiple, puede venir array. Nosotros SIEMPRE lo volcamos a pendingUploads.
     */
    public mixed $upload = null;

    /**
     * Buffer acumulado: aquí viven los ficheros “listos para enviar”.
     * Esto permite: seleccionar 1, luego otro, y que se SUMEN.
     */
    public array $pendingUploads = [];

    public int $uploadInputKey = 0;

    public bool $sendingFile = false;

    public string $tab = 'all'; // all|vinculados|sin_vincular


        protected function getHeaderActions(): array
    {
        return [
            Action::make('exportChat')
                ->label('Exportar chat')
                ->icon('heroicon-m-arrow-down-tray')
                ->visible(fn () => (bool) $this->selectedChatId && auth()->user()?->can('Chats:Export'))
                ->action(fn () => $this->exportSelectedChat()),

            Action::make('unlinkTelegram')
                ->label('Desvincular total')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => (bool) $this->selectedChatId
                    && (bool) ($this->selectedChat?->telegram_chat_id)
                    && (auth()->user()?->can('Chats:UnlinkTelegram') ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Desvincular Telegram (reset total)')
                ->modalDescription('Esto borra la vinculación actual (chat_id + thread_id) y caduca enlaces pendientes. El cliente tendrá que vincular de nuevo con un enlace nuevo.')
                ->modalSubmitActionLabel('Sí, desvincular')
            //    ->modalWidth('md')
                ->action(fn () => $this->unlinkSelectedTelegram()),    
        ];
    }

    public function setTab(string $tab): void
    {
        $allowed = ['all', 'vinculados', 'sin_vincular'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'all';

        // reset paginado de lista
        $this->chatsTake = 10;
    }



    public function getKpisProperty(): array
    {
        $base = $this->queryChatsForKpis();

        $total = (clone $base)->count();
        $sinVincular = (clone $base)->whereNull('telegram_chat_id')->count();
        $vinculados = max(0, $total - $sinVincular);

        $sinContestar = (clone $base)->where('pendiente_respuesta', true)->count();

        $topRow = (clone $base)
            ->where('pendiente_respuesta', true)
            ->selectRaw('asesor_id, COUNT(*) as c')
            ->groupBy('asesor_id')
            ->orderByDesc('c')
            ->first();

        $top = null;
        if ($topRow?->asesor_id) {
            $u = \App\Models\User::query()->select('id','name')->find($topRow->asesor_id);
            $top = ['name' => $u?->name ?? 'N/D', 'count' => (int) $topRow->c];
        }

        $over24h = (clone $base)
            ->where('pendiente_respuesta', true)
            ->whereNotNull('pendiente_since_at')
            ->where('pendiente_since_at', '<=', now()->subHours(24))
            ->count();

        $avgSec = (clone $base)
            ->whereNotNull('last_response_seconds')
            ->avg('last_response_seconds');

        $avgMin = $avgSec !== null ? (int) round(((float) $avgSec) / 60) : null;

        $worstRow = (clone $base)
            ->whereNotNull('last_response_seconds')
            ->selectRaw('asesor_id, AVG(last_response_seconds) as a')
            ->groupBy('asesor_id')
            ->orderByDesc('a')
            ->first();

        $worst = null;
        if ($worstRow?->asesor_id) {
            $u = \App\Models\User::query()->select('id','name')->find($worstRow->asesor_id);
            $worst = [
                'name' => $u?->name ?? 'N/D',
                'minutes' => (int) round(((float) $worstRow->a) / 60),
            ];
        }

        return [
            'chats_total' => $total,
            'sin_vincular' => $sinVincular,
            'vinculados' => $vinculados,

            'sin_contestar' => $sinContestar,
            'top_sin_contestar_asesor' => $top,

            'tiempo_medio_respuesta_min' => $avgMin,
            'peor_tiempo_asesor' => $worst,

            'sin_contestar_24h' => $over24h,
        ];
    }

        public function mount(): void
    {
        // Solo el asesor normal auto-crea chats para sus clientes
        if (! $this->canViewAllChats() && ! $this->canViewTeamChats()) {
            $this->ensureChatsForAssignedClients();
        }

        $first = $this->queryChats()->value('id');
        $this->selectedChatId = $first ?: null;
    }


        private function isSuperAdmin(): bool
    {
        $user = Auth::user();

        // Shield suele ir con Spatie Roles
        if ($user && method_exists($user, 'hasRole')) {
            return $user->hasRole('super_admin');
        }

        // Fallback por si tienes boolean
        return (bool) ($user->is_super_admin ?? false);
    }


    // ✅ ENVIAR TEXTO
    public function send(): void
    {
        $chat = $this->selectedChat;
        if (! $chat) {
            return;
        }

        $text = trim((string) $this->message);
        if ($text === '') {
            return;
        }

        // Caso "fake telegram" (tests / sandbox)
        if ($chat->telegram_chat_id && str_starts_with((string) $chat->telegram_chat_id, '999')) {
            ChatMensaje::create([
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'origen'  => 'asesor',
                'tipo'    => 'text',
                'contenido' => $text,
                'estado_envio' => 'sent',
            ]);

            $chat->update(['last_message_at' => now()]);

            $this->message = '';
            $this->dispatch('composer-reset'); // ✅ reset altura

            $this->markAsRead($chat->id);

            return;
        }

        // No vinculado -> mensaje sistema + reset
        if (! $chat->telegram_chat_id) {
            ChatMensaje::create([
                'chat_id' => $chat->id,
                'origen'  => 'sistema',
                'tipo'    => 'text',
                'contenido' => 'No se puede enviar: este chat no está vinculado a Telegram.',
            ]);

            $this->message = '';
            $this->dispatch('composer-reset'); // ✅ reset altura

            $this->markAsRead($chat->id);

            return;
        }

        $this->sending = true;

        try {
            $msg = ChatMensaje::create([
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'origen'  => 'asesor',
                'tipo'    => 'text',
                'contenido' => $text,
                'estado_envio' => 'pending',
            ]);

            $chat->update(['last_message_at' => now()]);

            $this->message = '';
            $this->dispatch('composer-reset'); // ✅ reset altura

            $this->markAsRead($chat->id);

            SendTelegramMessageJob::dispatch($msg->id);
        } finally {
            $this->sending = false;
        }
    }


    protected function rules(): array
    {
        // max en KB => 25MB = 25600 KB
        $rule = 'nullable|file|max:25600|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp';

        return [
            'upload' => $rule,
            'pendingUploads.*' => 'file|max:25600|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp',
        ];
    }
        //query para KPIs (sin búsquedas, paginación, etc)
        private function queryChatsForKpis()
    {
        return ChatConversacion::query()
            ->where('tipo', 'cliente')
            ->when(! $this->canViewAllChats(), function ($q) {
                if ($this->canViewTeamChats() && $this->isCoordinadorDeMiDepartamento()) {
                    $departamentoId = (int) (Auth::user()?->departamento_id ?? 0);

                    $q->whereHas('asesor', fn ($uq) => $uq->where('departamento_id', $departamentoId));
                    return;
                }

                $q->where('asesor_id', Auth::id());
            });
    }


    /**
     * Cada vez que el usuario selecciona archivo(s) en el input:
     * - validamos
     * - los pasamos a pendingUploads (ACUMULANDO)
     * - limpiamos $upload y forzamos reset del input
     */
    public function updatedUpload(): void
    {
        if (! $this->upload) {
            return;
        }

        $files = is_array($this->upload) ? $this->upload : [$this->upload];

        foreach ($files as $file) {
            try {
                // Validación por fichero (para poder sumar uno a uno sin romper)
                validator(
                    ['f' => $file],
                    ['f' => 'required|file|max:25600|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp']
                )->validate();
            } catch (\Illuminate\Validation\ValidationException $e) {
                $msg = $e->validator->errors()->first('f') ?: 'Archivo no válido.';

                Notification::make()
                    ->title('No se pudo adjuntar el archivo')
                    ->body($msg)
                    ->danger()
                    ->send();

                // Si uno falla, lo ignoramos y seguimos con los demás
                continue;
            }

            // ✅ Acumula
            $this->pendingUploads[] = $file;
        }

        // Limpia input temporal + fuerza a recrear el input
        $this->reset('upload');
        $this->resetErrorBag('upload');
        $this->uploadInputKey++;
    }

    /**
     * Borra 1 fichero del buffer (y su tmp físico).
     */
    public function removePendingUpload(int $index): void
    {
        if (! isset($this->pendingUploads[$index])) {
            return;
        }

        $file = $this->pendingUploads[$index];

        // Borrar tmp físico si Livewire lo soporta
        try {
            if (is_object($file) && method_exists($file, 'delete')) {
                $file->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('removePendingUpload: no se pudo borrar tmp', [
                'err' => $e->getMessage(),
            ]);
        }

        array_splice($this->pendingUploads, $index, 1);

        // Fuerza a recrear el input por si el navegador se queda pillado con el mismo fichero
        $this->uploadInputKey++;
        $this->dispatch('upload-cleared');
    }

    /**
     * Borra TODO el buffer.
     */
    public function clearUpload(): void
    {
        // 1) Borrar tmp físicos
        foreach ($this->pendingUploads as $file) {
            try {
                if (is_object($file) && method_exists($file, 'delete')) {
                    $file->delete();
                }
            } catch (\Throwable $e) {
                \Log::warning('clearUpload: no se pudo borrar tmp', [
                    'err' => $e->getMessage(),
                ]);
            }
        }

        // 2) Limpia estado
        $this->pendingUploads = [];
        $this->reset('upload');
        $this->resetErrorBag('upload');

        // 3) Fuerza a recrear el input
        $this->uploadInputKey++;

        // 4) Limpia value en frontend si hace falta
        $this->dispatch('upload-cleared');
    }

    /**
     * Envía TODOS los pendientes.
     */
    public function sendFiles(): void
    {
        $chat = $this->selectedChat;
        if (! $chat) {
            return;
        }

        if (! $chat->telegram_chat_id) {
            Notification::make()
                ->title('Chat sin vincular')
                ->body('No se puede enviar archivos hasta que el cliente vincule Telegram.')
                ->warning()
                ->send();

            $this->message = '';
            $this->clearUpload();
            $this->dispatch('composer-reset'); // ✅ reset altura textarea

            return;
        }

        if (count($this->pendingUploads) === 0) {
            Notification::make()
                ->title('No hay archivos seleccionados')
                ->warning()
                ->send();

            return;
        }

        $this->sendingFile = true;

        try {
            foreach ($this->pendingUploads as $file) {
                // Revalidar por seguridad
                validator(
                    ['f' => $file],
                    ['f' => 'required|file|max:25600|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp']
                )->validate();

                $size = (int) ($file->getSize() ?? 0);
                $mime = (string) ($file->getMimeType() ?? '');
                $originalName = (string) ($file->getClientOriginalName() ?? 'archivo');
                $ext = strtolower((string) ($file->getClientOriginalExtension() ?? ''));

                // 🚫 Bloqueo videos
                $videoExt = ['mp4','mov','mkv','avi','webm','m4v'];
                if (str_starts_with($mime, 'video/') || in_array($ext, $videoExt, true)) {
                    Notification::make()
                        ->title('Vídeos no permitidos')
                        ->body('Por privacidad y trazabilidad, este canal no admite vídeos.')
                        ->warning()
                        ->send();
                    continue;
                }

                // 🚫 Bloqueo GIF
                if ($ext === 'gif' || $mime === 'image/gif') {
                    Notification::make()
                        ->title('GIF no permitido')
                        ->body('No se admiten elementos animados por este canal.')
                        ->warning()
                        ->send();
                    continue;
                }

                $isImage = ($mime !== '' && str_starts_with($mime, 'image/'))
                    || in_array($ext, ['jpg','jpeg','png','webp'], true);

                $tipo = $isImage ? 'photo' : 'document';

                $folder = 'documentos/telegram/outgoing/chats/' . $chat->id . '/' . now()->format('Y/m');
                $finalExt = $ext !== '' ? $ext : ($isImage ? 'jpg' : 'bin');
                $filename = Str::uuid()->toString() . '.' . $finalExt;

                // ✅ Guardar en disk local (storage/app/private si tienes local apuntando ahí)
                $storedPath = $file->storeAs($folder, $filename, 'local');

                // Caption: lo que haya escrito (opcional)
                $caption = trim((string) $this->message);

                $preview = $caption !== ''
                    ? $caption
                    : ($tipo === 'photo' ? '📷 Foto' : ('📋 ' . $originalName));

                $msg = ChatMensaje::create([
                    'chat_id' => $chat->id,
                    'user_id' => Auth::id(),
                    'origen'  => 'asesor',
                    'tipo'    => $tipo,
                    'contenido' => $preview,
                    'caption'   => $caption !== '' ? $caption : null,

                    'file_path' => $storedPath,
                    'file_original_name' => $originalName,
                    'file_mime' => $mime !== '' ? $mime : null,
                    'file_size' => $size > 0 ? $size : null,

                    'estado_envio' => 'pending',
                ]);

                $chat->update(['last_message_at' => now()]);
                $this->markAsRead($chat->id);

                // Limpia tmp físico del fichero ya “consumido”
                try {
                    if (method_exists($file, 'delete')) {
                        $file->delete();
                    }
                } catch (\Throwable $e) {
                    // ok
                }

                // ✅ Cola normal (NO afterResponse) para ver reloj -> ✓
                SendTelegramMessageJob::dispatch($msg->id);
            }

            // ✅ Al final: limpiar buffer + texto
            $this->message = '';
            $this->clearUpload();
            $this->dispatch('composer-reset'); // ✅ reset altura textarea

        } finally {
            $this->sendingFile = false;
        }
    }

    public function relinkSelectedTelegram(): void
    {
        abort_unless(auth()->user()?->can('Chats:ReLinkTelegram'), 403);

        if (! $this->selectedChatId) return;

        $chat = $this->queryChats()->with('cliente')->whereKey($this->selectedChatId)->first();
        if (! $chat || ! $chat->cliente) {
            Notification::make()->title('Chat no encontrado')->danger()->send();
            return;
        }

        if (! $chat->telegram_chat_id) {
            Notification::make()->title('Este chat aún no está vinculado')->warning()->send();
            return;
        }

        $cliente = $chat->cliente;
        $email = trim((string) ($cliente->email_contacto ?? ''));
        if ($email === '') {
            Notification::make()
                ->title('El cliente no tiene email')
                ->body('Rellena email_contacto para poder enviar la re-vinculación.')
                ->warning()
                ->send();
            return;
        }

        $botUsername = config('services.telegram.bot_username', 'AsesorFyBot');

        try {
            DB::transaction(function () use ($cliente, $email, $botUsername, $chat) {
                $existingLink = TelegramLink::query()
                    ->where('cliente_id', $cliente->id)
                    ->where('token', 'like', 'relink_%')
                    ->whereNull('used_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->latest('id')
                    ->first();

                if ($existingLink) {
                    $url = "https://t.me/{$botUsername}?start={$existingLink->token}";
                    Mail::to($email)->send(new TelegramVinculacionMail($cliente, $url));

                    ChatMensaje::create([
                        'chat_id' => $chat->id,
                        'origen' => 'sistema',
                        'tipo' => 'text',
                        'contenido' => "Re-vinculación reenviada a {$email} (token existente).",
                    ]);
                    return;
                }

                $token = 'relink_' . Str::random(40);
                $tries = 0;
                while (TelegramLink::where('token', $token)->exists() && $tries < 5) {
                    $token = 'relink_' . Str::random(40);
                    $tries++;
                }

                $link = TelegramLink::create([
                    'cliente_id' => $cliente->id,
                    'token' => $token,
                    'expires_at' => now()->addDays(7),
                    'used_at' => null,
                ]);

                $url = "https://t.me/{$botUsername}?start={$link->token}";
                Mail::to($email)->send(new TelegramVinculacionMail($cliente, $url));

                ChatMensaje::create([
                    'chat_id' => $chat->id,
                    'origen' => 'sistema',
                    'tipo' => 'text',
                    'contenido' => "Re-vinculación enviada a {$email}.",
                ]);
            });

            Notification::make()
                ->title('Re-vinculación enviada')
                ->body("Se envió un enlace de re-vinculación a {$email}.")
                ->success()
                ->send();

            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error enviando re-vinculación')
                ->body(Str::limit($e->getMessage(), 220))
                ->danger()
                ->send();
        }
    }

public function unlinkSelectedTelegram(): void
    {
        abort_unless(auth()->user()?->can('Chats:UnlinkTelegram') ?? false, 403);

        if (! $this->selectedChatId) {
            return;
        }

        // Respetar permisos (admin/coordinador/asesor)
        $chat = $this->queryChats()
            ->with('cliente:id,razon_social,nombre,apellidos,email_contacto')
            ->whereKey($this->selectedChatId)
            ->first();

        if (! $chat || ! $chat->cliente_id) {
            Notification::make()->title('Chat no encontrado')->danger()->send();
            return;
        }

        if (! $chat->telegram_chat_id) {
            Notification::make()->title('Este chat ya está desvinculado')->warning()->send();
            return;
        }

        $oldTelegramChatId = $chat->telegram_chat_id;
        $clienteId = (int) $chat->cliente_id;
        
        // Sacamos el nombre para decírselo en el mensaje
        $nombreCliente = $chat->cliente->razon_social 
            ?? trim(($chat->cliente->nombre ?? '') . ' ' . ($chat->cliente->apellidos ?? '')) 
            ?: 'esta empresa';

        try {
            DB::transaction(function () use ($chat, $clienteId) {
                // 1) Reset vínculo en la conversación seleccionada
                $chat->update([
                    'telegram_chat_id'   => null,
                    'telegram_thread_id' => null,
                    'estado'             => 'abierta',
                    'unread_count'       => 0,
                    'last_message_at'    => $chat->last_message_at, // no tocamos histórico
                ]);

                // 2) Si por lo que sea existen otras conversaciones para ese cliente (raro), también las reseteamos
                ChatConversacion::query()
                    ->where('tipo', 'cliente')
                    ->where('cliente_id', $clienteId)
                    ->update([
                        'telegram_chat_id'   => null,
                        'telegram_thread_id' => null,
                    ]);

                // 3) Caducar links pendientes (normales y relink) para forzar enlace NUEVO
                TelegramLink::query()
                    ->where('cliente_id', $clienteId)
                    ->whereNull('used_at')
                    ->update([
                        'expires_at' => now(), // lo caducamos
                    ]);

                // 4) Log sistema
                ChatMensaje::create([
                    'chat_id'    => $chat->id,
                    'origen'     => 'sistema',
                    'tipo'       => 'text',
                    'contenido'  => '🔌 Telegram desvinculado (reset total). El cliente debe vincular de nuevo con un enlace nuevo.',
                    'leido'      => true,
                ]);
            });

            // 5) Comprobar si le quedan otras empresas vinculadas a este mismo Telegram
            $otrasEmpresas = ChatConversacion::query()
                ->where('telegram_chat_id', $oldTelegramChatId)
                ->where('id', '!=', $chat->id)
                ->exists();

            try {
                // Mensaje en texto plano puro con emojis, sin asteriscos ni HTML
                $mensajeAviso = $otrasEmpresas
                    ? "🔌 Tu empresa " . $nombreCliente . " ha sido desvinculada de AsesorFy.\n\nTus otras empresas siguen activas en sus respectivas carpetas."
                    : "🔌 Tu cuenta de " . $nombreCliente . " ha sido desvinculada completamente de AsesorFy.\n\nPara volver a escribir, solicita o usa un nuevo enlace de vinculación.";

                app(\App\Services\TelegramService::class)->sendMessage($oldTelegramChatId, $mensajeAviso);
            } catch (\Throwable $e) {
                // silencioso
            }

            Notification::make()
                ->title('Desvinculado')
                ->body('Vinculación eliminada. Ahora envía una invitación nueva para vincular de cero.')
                ->success()
                ->send();

            // refrescar UI
            $this->dispatch('$refresh');

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al desvincular')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 220))
                ->danger()
                ->send();
        }
    }
    public function sendAttachment(): void
    {
        // compatibilidad: el botón “Enviar archivo(s)” debe llamar a sendFiles()
        $this->sendFiles();
    }

    public function updatedSearch(): void
    {
        $this->chatsTake = 10;
         // si buscamos, queremos ver TODO (sin filtrar por tabs)
        if (trim($this->search) !== '') {
            $this->tab = 'all';
        }
    }

    public function loadMoreChats(): void
    {
        $this->chatsTake = min($this->chatsTake + $this->chatsStep, $this->chatsTakeMax);
    }

    public function markSelectedChatAsRead(): void
    {
        abort_unless(auth()->user()?->can('Chats:MarkRead'), 403);

        if (! $this->selectedChatId) return;

        $this->markAsRead($this->selectedChatId);
        $this->dispatch('$refresh');
    }

    public function invite(int $chatId): void
    {
        $chat = $this->queryChats()->with('cliente')->whereKey($chatId)->first();

        if (! $chat || ! $chat->cliente) {
            Notification::make()->title('Chat no encontrado')->danger()->send();
            return;
        }

        if ($chat->telegram_chat_id) {
            Notification::make()->title('Este cliente ya está vinculado a Telegram')->success()->send();
            return;
        }

        $cliente = $chat->cliente;
        $email = trim((string) ($cliente->email_contacto ?? ''));

        if ($email === '') {
            Notification::make()
                ->title('El cliente no tiene email')
                ->body('Rellena email_contacto para poder enviar la invitación.')
                ->warning()
                ->send();
            return;
        }

        $botUsername = config('services.telegram.bot_username', 'AsesorFyBot');

        try {
            DB::transaction(function () use ($cliente, $email, $botUsername, $chat) {
                $existingLink = TelegramLink::query()
                    ->where('cliente_id', $cliente->id)
                    ->whereNull('used_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->latest('id')
                    ->first();

                if ($existingLink) {
                    $url = "https://t.me/{$botUsername}?start={$existingLink->token}";
                    Mail::to($email)->send(new TelegramVinculacionMail($cliente, $url));

                    ChatMensaje::create([
                        'chat_id' => $chat->id,
                        'origen' => 'sistema',
                        'tipo' => 'text',
                        'contenido' => "Invitación reenviada a {$email} (token ya existente).",
                    ]);
                    return;
                }

                $token = Str::random(48);
                $tries = 0;
                while (TelegramLink::where('token', $token)->exists() && $tries < 5) {
                    $token = Str::random(48);
                    $tries++;
                }

                $link = TelegramLink::create([
                    'cliente_id' => $cliente->id,
                    'token' => $token,
                    'expires_at' => now()->addDays(7),
                    'used_at' => null,
                ]);

                $url = "https://t.me/{$botUsername}?start={$link->token}";
                Mail::to($email)->send(new TelegramVinculacionMail($cliente, $url));

                ChatMensaje::create([
                    'chat_id' => $chat->id,
                    'origen' => 'sistema',
                    'tipo' => 'text',
                    'contenido' => "Invitación enviada a {$email}.",
                ]);
            });

            Notification::make()
                ->title('Invitación enviada')
                ->body("Se envió el enlace de Telegram a {$email}.")
                ->success()
                ->send();

            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error enviando invitación')
                ->body(Str::limit($e->getMessage(), 220))
                ->danger()
                ->send();
        }
    }

    public function getChatsProperty()
    {
        return $this->queryChats()
            ->with('cliente:id,razon_social,nombre,apellidos,email_contacto')
            ->addSelect([
                'last_text' => ChatMensaje::query()
                    ->selectRaw("
                        CASE
                            WHEN contenido IS NOT NULL AND contenido <> '' THEN contenido
                            WHEN tipo = 'photo' THEN '📷 Foto'
                            WHEN file_path IS NOT NULL THEN CONCAT('📋 ', COALESCE(file_original_name, 'Documento'))
                            ELSE '—'
                        END
                    ")
                    ->whereColumn('chat_id', 'chat_conversaciones.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])

            // ✅ Inbox:
            // 1) pendientes arriba
            // 2) pendientes: más antiguos primero (pendiente_since_at ASC)
            // 3) no pendientes: más recientes arriba (last_message_at DESC)
            ->orderByDesc('pendiente_respuesta')
            ->orderByRaw('pendiente_since_at IS NULL ASC')
            ->orderBy('pendiente_since_at')
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')

            ->take($this->chatsTake)
            ->get();
    }



    public function getHasMoreChatsProperty(): bool
        {
            return $this->queryChats()
                ->skip($this->chatsTake)
                ->take(1)
                ->exists();
        }


    public function getSelectedChatProperty(): ?ChatConversacion
    {
        if (! $this->selectedChatId) return null;

        return $this->queryChats()
            ->withCount('mensajes')
            ->with([
                'cliente:id,razon_social,nombre,apellidos,email_contacto',
                'mensajes' => function ($q) {
                    $q->reorder('id', 'desc')->limit($this->messagesTake);
                },
            ])
            ->where('id', $this->selectedChatId)
            ->first();
    }

    public function getHasOlderMessagesProperty(): bool
    {
        $chat = $this->selectedChat;
        if (! $chat) return false;

        return (int) ($chat->mensajes_count ?? 0) > $this->messagesTake;
    }

    public function selectChat(int $chatId): void
    {
        $this->selectedChatId = $chatId;
        $this->messagesTake = 80;

        // opcional: al cambiar de chat, limpia adjuntos pendientes
        $this->clearUpload();
    }

    private function markAsRead(int $chatId): void
    {
        DB::transaction(function () use ($chatId) {
            DB::table('chat_conversaciones')->where('id', $chatId)->update([
                'unread_count' => 0,
                'updated_at' => now(),
            ]);

            DB::table('chat_mensajes')
                ->where('chat_id', $chatId)
                ->where('origen', 'cliente')
                ->where('leido', false)
                ->update([
                    'leido' => true,
                    'updated_at' => now(),
                ]);
        });
    }

    public function loadOlderMessages(): void
    {
        $this->messagesTake = min($this->messagesTake + $this->messagesStep, $this->messagesTakeMax);
       
    }

        private function canViewAllChats(): bool
    {
        return Auth::user()?->can('Chats:ViewAll') ?? false;
    }

    private function canViewTeamChats(): bool
    {
        return Auth::user()?->can('Chats:ViewTeam') ?? false;
    }

    private function isCoordinadorDeMiDepartamento(): bool
    {
        $user = Auth::user();
        if (! $user) return false;

        $depId = (int) ($user->departamento_id ?? 0);
        if ($depId === 0) return false;

        return (int) ($user->departamento?->coordinador_id ?? 0) === (int) $user->id;
    }


        private function queryChats()
        {
            return ChatConversacion::query()
                ->where('tipo', 'cliente')
                ->when(! $this->canViewAllChats(), function ($q) {

                    if ($this->canViewTeamChats() && $this->isCoordinadorDeMiDepartamento()) {
                        $departamentoId = (int) (Auth::user()?->departamento_id ?? 0);
                        $q->whereHas('asesor', fn ($uq) => $uq->where('departamento_id', $departamentoId));
                        return;
                    }

                    $q->where('asesor_id', Auth::id());
                })

                // ✅ Tabs: solo cuando NO hay búsqueda
                ->when(trim($this->search) === '', function ($q) {
                    if ($this->tab === 'vinculados') {
                        $q->whereNotNull('telegram_chat_id');
                    } elseif ($this->tab === 'sin_vincular') {
                        $q->whereNull('telegram_chat_id');
                    }
                })

                ->when($this->search !== '', function ($q) {
                    $q->whereHas('cliente', function ($cq) {
                        $s = $this->search;

                        $cq->where('razon_social', 'like', "%{$s}%")
                            ->orWhere('nombre', 'like', "%{$s}%")
                            ->orWhere('apellidos', 'like', "%{$s}%")
                            ->orWhere('dni_cif', 'like', "%{$s}%");
                    });
                })

                // OJO: aquí NO ordenamos, lo hacemos en getChatsProperty()
                ;
        }



    private function ensureChatsForAssignedClients(): void
    {
        $asesorId = Auth::id();

        $clienteIds = Cliente::query()
            ->where('asesor_id', $asesorId)
            ->pluck('id');

        if ($clienteIds->isEmpty()) return;

        $existingClienteIds = ChatConversacion::query()
            ->where('tipo', 'cliente')
            ->where('asesor_id', $asesorId)
            ->whereIn('cliente_id', $clienteIds)
            ->pluck('cliente_id');

        $missing = $clienteIds->diff($existingClienteIds);
        if ($missing->isEmpty()) return;

        $now = now();

        $rows = $missing->map(fn (int $clienteId) => [
            'tipo' => 'cliente',
            'cliente_id' => $clienteId,
            'asesor_id' => $asesorId,
            'telegram_chat_id' => null,
            'estado' => 'abierta',
            'unread_count' => 0,
            'last_message_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('chat_conversaciones')->insert($rows);
    }

        public function exportSelectedChat(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('Chats:Export'), 403);

        if (! $this->selectedChatId) {
            abort(404);
        }

        // ✅ IMPORTANTÍSIMO: usar queryChats() para respetar permisos (admin/coordinador/asesor)
        $chat = $this->queryChats()
            ->with([
                'cliente:id,razon_social,nombre,apellidos,dni_cif',
                'asesor:id,name',
                 'mensajes' => fn ($q) => $q->orderBy('id')->with('user:id,name'),
            ])
            ->whereKey($this->selectedChatId)
            ->firstOrFail();

        $clienteNombre = trim(implode(' ', array_filter([
            $chat->cliente?->razon_social,
            $chat->cliente?->nombre,
            $chat->cliente?->apellidos,
        ])));

        $header = [];
        $header[] = 'AsesorFy — Export chat';
        $header[] = 'Chat ID: ' . $chat->id;
        $header[] = 'Cliente: ' . ($clienteNombre !== '' ? $clienteNombre : 'N/D');
        $header[] = 'Cliente DNI/CIF: ' . ($chat->cliente?->dni_cif ?? 'N/D');
        $header[] = 'Asesor: ' . ($chat->asesor?->name ?? 'N/D');
        $header[] = 'Exportado: ' . now()->format('Y-m-d H:i:s');
        $header[] = str_repeat('-', 70);

        $lines = $header;

        foreach ($chat->mensajes as $m) {
            $ts = optional($m->created_at)->format('d-m-Y H:i:s') ?? 'N/D';

            $who = match ($m->origen) {
                'cliente' => 'Cliente',
                'asesor' => $m->user?->name
                        ? ('Asesor: ' . $m->user->name)
                        : ($chat->asesor?->name ? ('Asesor: ' . $chat->asesor->name) : 'Asesor'),
                'sistema' => 'Sistema',
                default => $m->origen ?: 'N/D',
            };

            $text = trim((string) ($m->contenido ?? ''));
            if ($text === '') {
                $text = '[sin texto]';
            }

            if (! empty($m->file_original_name)) {
                $text .= ' [adjunto: ' . $m->file_original_name . ']';
            }

            $lines[] = "[$ts] $who: $text";
        }

        $payload = implode("\n", $lines) . "\n";

        $safeCliente = $chat->cliente?->dni_cif ?: ('chat_' . $chat->id);
        $filename = 'chat_' . $safeCliente . '_' . now()->format('Ymd_His') . '.txt';

        return response()->streamDownload(
            fn () => print($payload),
            $filename,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

}
