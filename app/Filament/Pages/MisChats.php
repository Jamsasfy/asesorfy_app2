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

class MisChats extends Page
{
    use WithFileUploads, HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
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

    public function mount(): void
    {
        $this->ensureChatsForAssignedClients();

        $first = $this->queryChats()->value('id');
        $this->selectedChatId = $first ?: null;
    }

    // ✅ ENVIAR TEXTO
    public function send(): void
    {
        $chat = $this->selectedChat;
        if (! $chat) return;

        $text = trim($this->message);
        if ($text === '') return;

        if ($chat->telegram_chat_id && str_starts_with((string) $chat->telegram_chat_id, '999')) {
            ChatMensaje::create([
                'chat_id' => $chat->id,
                'origen' => 'asesor',
                'tipo' => 'text',
                'contenido' => $text,
                'estado_envio' => 'sent',
            ]);

            $chat->update(['last_message_at' => now()]);
            $this->message = '';
            $this->markAsRead($chat->id);
            return;
        }

        if (! $chat->telegram_chat_id) {
            ChatMensaje::create([
                'chat_id' => $chat->id,
                'origen' => 'sistema',
                'tipo' => 'text',
                'contenido' => 'No se puede enviar: este chat no está vinculado a Telegram.',
            ]);

            $this->message = '';
            $this->markAsRead($chat->id);
            return;
        }

        $this->sending = true;

        $msg = ChatMensaje::create([
            'chat_id' => $chat->id,
            'origen' => 'asesor',
            'tipo' => 'text',
            'contenido' => $text,
            'estado_envio' => 'pending',
        ]);

        $chat->update(['last_message_at' => now()]);
        $this->sending = false;
        $this->message = '';
        $this->markAsRead($chat->id);

        SendTelegramMessageJob::dispatchSync($msg->id);
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
        if (! $chat) return;

        if (! $chat->telegram_chat_id) {
            Notification::make()
                ->title('Chat sin vincular')
                ->body('No se puede enviar archivos hasta que el cliente vincule Telegram.')
                ->warning()
                ->send();

            $this->clearUpload();
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

                // ✅ Guardar en disk local (root storage/app/private)
                $storedPath = $file->storeAs($folder, $filename, 'local');

                // Caption: lo que haya escrito (opcional)
                $caption = trim((string) $this->message);

                $preview = $caption !== ''
                    ? $caption
                    : ($tipo === 'photo' ? '📷 Foto' : ('📋 ' . $originalName));

                $msg = ChatMensaje::create([
                    'chat_id' => $chat->id,
                    'origen' => 'asesor',
                    'tipo' => $tipo,
                    'contenido' => $preview,
                    'caption' => $caption !== '' ? $caption : null,

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
                    // no pasa nada, Livewire limpiará luego, pero intentamos
                }

                SendTelegramMessageJob::dispatch($msg->id)->afterResponse();
            }

            // ✅ Al final: limpiar buffer + texto
            $this->message = '';
            $this->clearUpload();

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

    public function sendAttachment(): void
    {
        // compatibilidad: el botón “Enviar archivo(s)” debe llamar a sendFiles()
        $this->sendFiles();
    }

    public function updatedSearch(): void
    {
        $this->chatsTake = 10;
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
                    ->select('contenido')
                    ->whereColumn('chat_id', 'chat_conversaciones.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->take($this->chatsTake)
            ->get();
    }

    public function getHasMoreChatsProperty(): bool
    {
        return $this->queryChats()->count() > $this->chatsTake;
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
        $this->dispatch('$refresh');
    }

    private function queryChats()
    {
        return ChatConversacion::query()
            ->where('tipo', 'cliente')
            ->where('asesor_id', Auth::id())
            ->when($this->search !== '', function ($q) {
                $q->whereHas('cliente', function ($cq) {
                    $s = $this->search;

                    $cq->where('razon_social', 'like', "%{$s}%")
                        ->orWhere('nombre', 'like', "%{$s}%")
                        ->orWhere('apellidos', 'like', "%{$s}%")
                        ->orWhere('dni_cif', 'like', "%{$s}%");
                });
            })
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderByDesc('last_message_at');
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
}
