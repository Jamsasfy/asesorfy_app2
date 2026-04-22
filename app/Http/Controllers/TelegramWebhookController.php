<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\TelegramLink;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ChatMensaje;
use App\Models\ChatConversacion;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1) Seguridad: comprobar secret header (setWebhook secret token)
        $secret = (string) config('services.telegram.webhook_secret', env('TELEGRAM_WEBHOOK_SECRET'));
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret !== '' && ! hash_equals($secret, $header)) {
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();
        $updateId = data_get($update, 'update_id');

        // 2) Idempotencia: si ya procesamos este update_id, salimos OK
        if ($updateId && ChatMensaje::query()->where('telegram_update_id', $updateId)->exists()) {
            return response()->json(['ok' => true]);
        }

        // 3) Telegram puede mandar distintos tipos. Para MVP usamos message o edited_message.
        $message = data_get($update, 'message') ?: data_get($update, 'edited_message');
        if (! $message) {
            return response()->json(['ok' => true]);
        }

        $telegramChatId = (int) data_get($message, 'chat.id');
        $telegramMessageId = (int) data_get($message, 'message_id');

        // ✅ TOPICS: extraer message_thread_id del update
        $incomingThreadId = data_get($message, 'message_thread_id');
        $incomingThreadId = $incomingThreadId !== null ? (int) $incomingThreadId : null;

        $text = trim((string) data_get($message, 'text', ''));
        $caption = trim((string) data_get($message, 'caption', ''));

        // Extracción de datos del usuario
        $telegramUserId = (int) data_get($message, 'from.id');
        $telegramUsername = (string) data_get($message, 'from.username', '');
        $telegramFirstName = (string) data_get($message, 'from.first_name', '');

        // Textos estándar
        $txtAudioBlocked = "🛡️ Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite mensajes de audio. Por favor, envía tu consulta por texto o adjunta la documentación necesaria. Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy. 🔒";

        $txtVideoBlocked = "🔒 Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite vídeos.\n\nPor favor, envía tu consulta por texto o adjunta la documentación necesaria (PDF, Word o Excel). Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy.";

        $txtAnimBlocked = "🔒 Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite GIFs, stickers ni elementos animados.\n\nPor favor, envía tu consulta por texto o adjunta la documentación necesaria. Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy.";

        // 3.5) Bloqueo: NO aceptamos audios (voice/audio)
        $hasVoice = (bool) data_get($message, 'voice');
        $hasAudio = (bool) data_get($message, 'audio');

        if ($hasVoice || $hasAudio) {
            [$chat, $alreadyHandled] = $this->findConversacion($telegramChatId, $incomingThreadId);
            if ($alreadyHandled) {
                return response()->json(['ok' => true]);
            }

            if ($chat) {
                ChatMensaje::create([
                    'chat_id'             => $chat->id,
                    'origen'              => 'sistema',
                    'tipo'                => 'text',
                    'contenido'           => 'El cliente intentó enviar un audio (bloqueado).',
                    'telegram_message_id' => $telegramMessageId ?: null,
                    'telegram_update_id'  => null,
                    'payload'             => json_encode($update),
                    'leido'               => true,
                ]);

                $this->bumpChatCounters((int) $chat->id, $telegramUsername, $telegramFirstName);
            }

            $this->replyTelegram($telegramChatId, $txtAudioBlocked, $incomingThreadId);
            return response()->json(['ok' => true]);
        }

        // 3.6) Bloqueo: NO aceptamos vídeos (video / video_note / animation)
        $hasVideo = (bool) data_get($message, 'video');
        $hasVideoNote = (bool) data_get($message, 'video_note');
        $hasAnimation = (bool) data_get($message, 'animation');

        if ($hasVideo || $hasVideoNote || $hasAnimation) {
            [$chat, $alreadyHandled] = $this->findConversacion($telegramChatId, $incomingThreadId);
            if ($alreadyHandled) {
                return response()->json(['ok' => true]);
            }

            if ($chat) {
                ChatMensaje::create([
                    'chat_id'             => $chat->id,
                    'origen'              => 'sistema',
                    'tipo'                => 'text',
                    'contenido'           => 'El cliente intentó enviar un vídeo/GIF (bloqueado).',
                    'telegram_message_id' => $telegramMessageId ?: null,
                    'telegram_update_id'  => null,
                    'payload'             => json_encode($update),
                    'leido'               => true,
                ]);

                $this->bumpChatCounters((int) $chat->id, $telegramUsername, $telegramFirstName);
            }

            $this->replyTelegram($telegramChatId, $hasAnimation ? $txtAnimBlocked : $txtVideoBlocked, $incomingThreadId);
            return response()->json(['ok' => true]);
        }

        // 3.7) Bloqueo: GIFs / stickers / animaciones
        $hasSticker = (bool) data_get($message, 'sticker');
        $hasDice = (bool) data_get($message, 'dice');
        $entities = (array) data_get($message, 'entities', []);
        $captionEntities = (array) data_get($message, 'caption_entities', []);

        $hasCustomEmoji = collect(array_merge($entities, $captionEntities))
            ->contains(fn ($e) => (string) data_get($e, 'type') === 'custom_emoji');

        if ($hasSticker || $hasDice || $hasCustomEmoji) {
            $this->replyTelegram($telegramChatId, $txtAnimBlocked, $incomingThreadId);
            return response()->json(['ok' => true]);
        }

        // 4) Caso /start <token> => vinculación
        if (str_starts_with($text, '/start')) {
            $parts = preg_split('/\s+/', $text);
            $token = $parts[1] ?? null;

            if ($token) {
                $this->handleStartToken(
                    token: $token,
                    telegramChatId: $telegramChatId,
                    updateId: $updateId,
                    telegramMessageId: $telegramMessageId,
                    update: $update,
                    telegramUserId: $telegramUserId,
                    telegramUsername: $telegramUsername,
                    telegramFirstName: $telegramFirstName
                );
            } else {
                $this->replyTelegram($telegramChatId, '❌ Enlace inválido. Pide uno nuevo a tu asesor.', $incomingThreadId);
            }

            return response()->json(['ok' => true]);
        }

        // 5) Localizar conversación por telegram_chat_id + thread_id
        [$chat, $alreadyHandled] = $this->findConversacion($telegramChatId, $incomingThreadId);

        if (! $chat) {
            if (! $alreadyHandled) {
                $this->replyTelegram(
                    $telegramChatId,
                    '⚠️ Esta cuenta de Telegram no está vinculada a AsesorFy. Usa el enlace más reciente o pide uno nuevo a tu asesor.',
                    $incomingThreadId
                );
            }
            return response()->json(['ok' => true]);
        }

        // 6) Validación de documentos (allowlist + tamaño) ANTES de guardar
        $document = data_get($message, 'document');
        if ($document) {
            $mime = (string) data_get($document, 'mime_type', '');
            $fileName = (string) data_get($document, 'file_name', '');
            $fileSize = (int) data_get($document, 'file_size', 0);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $videoExt = ['mp4', 'mov', 'mkv', 'avi', 'webm', 'm4v'];
            $isVideoDoc = str_starts_with($mime, 'video/')
                || in_array($ext, $videoExt, true)
                || in_array($mime, ['application/x-matroska'], true);

            if ($isVideoDoc) {
                $this->replyTelegram($telegramChatId, $txtVideoBlocked, $incomingThreadId);
                return response()->json(['ok' => true]);
            }

            $maxBytes = 25 * 1024 * 1024;
            if ($fileSize > $maxBytes) {
                $this->replyTelegram(
                    $telegramChatId,
                    "🔒 Archivo demasiado grande. El tamaño máximo permitido es 25 MB.\n\nPor favor, envía un archivo más ligero o divídelo en varios.",
                    $incomingThreadId
                );
                return response()->json(['ok' => true]);
            }

            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/webp',
            ];

            $allowedExt = [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt',
                'jpg', 'jpeg', 'png', 'webp',
            ];

            $mimeOk = ($mime !== '' && in_array($mime, $allowedMimes, true));
            $extOk = ($ext !== '' && in_array($ext, $allowedExt, true));

            if (! $mimeOk && ! $extOk) {
                $this->replyTelegram(
                    $telegramChatId,
                    "🔒 Formato no admitido por motivos de seguridad.\n\nFormatos permitidos: PDF, Word (DOC/DOCX), Excel (XLS/XLSX), TXT e imágenes (JPG/PNG/WEBP).\nTamaño máximo: 25 MB.",
                    $incomingThreadId
                );
                return response()->json(['ok' => true]);
            }
        }

        // 7) Detectar adjuntos (document / photo)
        $photos = data_get($message, 'photo');

        // --- A) DOCUMENTO ---
        if (is_array($document) && ! empty($document['file_id'])) {
            $this->storeIncomingAttachment(
                chatId: (int) $chat->id,
                tipo: 'document',
                fileId: (string) ($document['file_id'] ?? ''),
                fileUniqueId: (string) ($document['file_unique_id'] ?? ''),
                originalName: (string) ($document['file_name'] ?? 'documento'),
                mime: (string) ($document['mime_type'] ?? ''),
                size: (int) ($document['file_size'] ?? 0),
                caption: $caption,
                telegramMessageId: $telegramMessageId,
                updateId: $updateId,
                update: $update
            );

            $this->bumpChatCounters((int) $chat->id, $telegramUsername, $telegramFirstName);
            return response()->json(['ok' => true]);
        }

        // --- B) FOTO ---
        if (is_array($photos) && count($photos) > 0) {
            $best = end($photos);
            $fileId = (string) data_get($best, 'file_id', '');
            $fileUniqueId = (string) data_get($best, 'file_unique_id', '');
            $size = (int) data_get($best, 'file_size', 0);

            if ($fileId !== '') {
                $this->storeIncomingAttachment(
                    chatId: (int) $chat->id,
                    tipo: 'photo',
                    fileId: $fileId,
                    fileUniqueId: $fileUniqueId,
                    originalName: 'foto',
                    mime: '',
                    size: $size,
                    caption: $caption,
                    telegramMessageId: $telegramMessageId,
                    updateId: $updateId,
                    update: $update
                );

                $this->bumpChatCounters((int) $chat->id, $telegramUsername, $telegramFirstName);
                return response()->json(['ok' => true]);
            }
        }

        // --- C) TEXTO ---
        if ($text === '') {
            return response()->json(['ok' => true]);
        }

        ChatMensaje::create([
            'chat_id'             => $chat->id,
            'origen'              => 'cliente',
            'tipo'                => 'text',
            'contenido'           => $text,
            'telegram_message_id' => $telegramMessageId ?: null,
            'telegram_update_id'  => $updateId ?: null,
            'payload'             => json_encode($update),
            'leido'               => false,
        ]);

        $this->bumpChatCounters((int) $chat->id, $telegramUsername, $telegramFirstName);

        return response()->json(['ok' => true]);
    }

    // =========================================================================
    //  Búsqueda de Conversación Unificada (ENRUTAMIENTO ESTRICTO)
    // =========================================================================

    private function findConversacion(int $telegramChatId, ?int $threadId): array
    {
        $convs = ChatConversacion::query()
            ->where('telegram_chat_id', $telegramChatId)
            ->get();

        if ($convs->count() === 0) {
            return [null, false];
        }

        $isGeneral = ($threadId === null) || ($threadId === 1);

        // ==========================================
        // REGLA 1 y REGLA 3: UN SOLO CLIENTE ACTIVO
        // ==========================================
        if ($convs->count() === 1) {
            $conv = $convs->first();
            $nombreEmpresa = $conv->cliente?->razon_social ?: 'tu empresa';

            // REGLA 1: Tubo único puro (nunca tuvo carpetas)
            if (empty($conv->telegram_thread_id)) {
                if ($isGeneral) {
                    return [$conv, false]; // Todo OK
                } else {
                    // Escribió en una carpeta fantasma (glitch o chat residual muy raro)
                    $this->replyTelegram(
                        $telegramChatId, 
                        "🔒 <b>Chat inactivo (Histórico)</b>\n\nPor favor, sal de esta carpeta y escríbenos desde el chat principal de <b>{$nombreEmpresa}</b>.", 
                        $threadId
                    );
                    return [null, true];
                }
            }

            // REGLA 3: Fue multi-empresa, ahora solo tiene una activa (Tiene carpeta en BD)
            $goodThread = (int) $conv->telegram_thread_id;

            if ($isGeneral) {
                $this->replyTelegram(
                    $telegramChatId,
                    "👋 <b>Hola. Este espacio General es solo para avisos del sistema.</b>\n\nPor favor, entra directamente en la carpeta de <b>{$nombreEmpresa}</b> y escríbenos por ahí para poder atenderte.",
                    $threadId
                );
                return [null, true];
            }

            if ($threadId === $goodThread) {
                return [$conv, false]; // Escribió en la carpeta correcta
            }

            // Escribió en una carpeta desvinculada (Histórico)
            $this->replyTelegram(
                $telegramChatId,
                "🔒 <b>Chat inactivo (Histórico)</b>\n\nEste chat corresponde a una vinculación antigua y se mantiene en tu móvil por seguridad para que tengas acceso a tus mensajes.\n\nPara hablar con tu asesor, ve a la carpeta activa de <b>{$nombreEmpresa}</b>.",
                $threadId
            );
            return [null, true];
        }

        // ==========================================
        // REGLA 2: MULTI-EMPRESA (>1 clientes activos)
        // ==========================================
        $listaEmpresas = $convs->map(fn ($c) => "• <b>" . ($c->cliente->razon_social ?? "Cliente #{$c->cliente_id}") . "</b>")->implode("\n");

        if ($isGeneral) {
            $this->replyTelegram(
                $telegramChatId,
                "👋 <b>Hola. Este espacio General es solo para avisos del sistema.</b>\n\nTienes varias empresas vinculadas. Por favor, entra y escribe directamente dentro de la carpeta correspondiente a la consulta:\n\n{$listaEmpresas}",
                $threadId
            );
            return [null, true];
        }

        $chat = $convs->firstWhere('telegram_thread_id', $threadId);
        if ($chat) {
            return [$chat, false]; // Escribió en una de las carpetas activas correctas
        }

        // Escribió en una carpeta que ya no está en BD (Desvinculada)
        $this->replyTelegram(
            $telegramChatId,
            "🔒 <b>Chat inactivo (Histórico)</b>\n\nEste chat corresponde a una vinculación antigua y se mantiene en tu móvil por seguridad para que tengas acceso a tus mensajes.\n\nPor favor, usa las carpetas activas de tus empresas:\n\n{$listaEmpresas}",
            $threadId
        );

        return [null, true];
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function bumpChatCounters(int $chatId, string $username = '', string $firstName = ''): void
    {
        $updateData = [
            'unread_count'    => DB::raw('unread_count + 1'),
            'last_message_at' => now(),
            'updated_at'      => now(),
        ];

        // Autoguardado silencioso de información de Telegram si ha cambiado
        if ($username !== '' || $firstName !== '') {
            $chat = DB::table('chat_conversaciones')->where('id', $chatId)->first();
            if ($chat) {
                if ($username !== '' && $chat->telegram_username !== $username) {
                    $updateData['telegram_username'] = ltrim($username, '@');
                }
                if ($firstName !== '' && $chat->telegram_first_name !== $firstName) {
                    $updateData['telegram_first_name'] = $firstName;
                }
            }
        }

        DB::table('chat_conversaciones')->where('id', $chatId)->update($updateData);
    }

    private function storeIncomingAttachment(
        int $chatId,
        string $tipo,
        string $fileId,
        string $fileUniqueId,
        string $originalName,
        string $mime,
        int $size,
        string $caption,
        int $telegramMessageId,
        ?int $updateId,
        array $update
    ): void {
        $tg = app(TelegramService::class);

        $fileInfo = $tg->getFile($fileId);
        $filePathOnTelegram = (string) data_get($fileInfo, 'result.file_path', '');

        if ($filePathOnTelegram === '') {
            return;
        }

        $ext = pathinfo($filePathOnTelegram, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? $ext : ($tipo === 'photo' ? 'jpg' : 'bin');

        if ($mime === '') {
            $mime = match (strtolower($ext)) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };
        }

        $sizeFromTg = (int) data_get($fileInfo, 'result.file_size', 0);
        if ($sizeFromTg > 0) {
            $size = $sizeFromTg;
        }

        $rel = 'documentos/telegram/chats/' . $chatId . '/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.' . $ext;
        $tg->downloadFileToStorage($filePathOnTelegram, $rel);

        $preview = trim($caption) !== ''
            ? trim($caption)
            : ($tipo === 'photo' ? '📷 Foto' : ('📎 ' . ($originalName ?: 'Documento')));

        ChatMensaje::create([
            'chat_id'                   => $chatId,
            'origen'                    => 'cliente',
            'tipo'                      => $tipo,
            'contenido'                 => $preview,
            'caption'                   => $caption !== '' ? $caption : null,
            'file_path'                 => $rel,
            'file_original_name'        => $originalName !== '' ? $originalName : null,
            'file_mime'                 => $mime !== '' ? $mime : null,
            'file_size'                 => $size > 0 ? $size : null,
            'telegram_message_id'       => $telegramMessageId ?: null,
            'telegram_update_id'        => $updateId ?: null,
            'telegram_file_id'          => $fileId ?: null,
            'telegram_file_unique_id'   => $fileUniqueId ?: null,
            'payload'                   => json_encode($update),
            'leido'                     => false,
        ]);
    }

    // =========================================================================
    //  Vinculación
    // =========================================================================

    private function handleStartToken(
        string $token,
        int $telegramChatId,
        ?int $updateId,
        int $telegramMessageId,
        array $update,
        int $telegramUserId = 0,
        string $telegramUsername = '',
        string $telegramFirstName = ''
    ): void {
        \Illuminate\Support\Facades\Log::info('Webhook ejecutado: Buscando token', [
            'token_recibido' => $token,
            'bd_activa'      => \Illuminate\Support\Facades\DB::connection()->getDatabaseName()
        ]);

        $isRelink = str_starts_with($token, 'relink_');

        $link = TelegramLink::where('token', $token)->first();
        if (! $link) {
            $this->replyTelegram($telegramChatId, '❌ Enlace inválido o caducado. Pide uno nuevo a tu asesor.');
            return;
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            $this->replyTelegram($telegramChatId, '❌ Este enlace ha caducado. Pide uno nuevo a tu asesor.');
            return;
        }

        $cliente = Cliente::find($link->cliente_id);
        if (! $cliente) {
            $this->replyTelegram($telegramChatId, '❌ Este enlace no es válido. Pide uno nuevo a tu asesor.');
            return;
        }

        if (! $cliente->asesor_id) {
            $this->replyTelegram(
                $telegramChatId,
                '⚠️ Aún no tienes un asesor asignado en AsesorFy. Contacta con atención al cliente para que te asignen uno antes de vincular Telegram.'
            );
            return;
        }

        // ESCUDO DE SEGURIDAD CROSS-IDENTITY
        $existingOtherCompanies = ChatConversacion::query()
            ->where('telegram_chat_id', $telegramChatId)
            ->where('tipo', 'cliente')
            ->where('cliente_id', '!=', $cliente->id)
            ->with('cliente.usuarios')
            ->get();

        if ($existingOtherCompanies->isNotEmpty()) {
            $newUserIds = $cliente->usuarios->pluck('id')->push($cliente->user_id)->filter()->unique()->toArray();

            $sharesIdentity = false;
            foreach ($existingOtherCompanies as $otherChat) {
                $otherClient = $otherChat->cliente;
                if ($otherClient) {
                    $otherUserIds = $otherClient->usuarios->pluck('id')->push($otherClient->user_id)->filter()->unique()->toArray();
                    
                    if (!empty(array_intersect($newUserIds, $otherUserIds))) {
                        $sharesIdentity = true;
                        break;
                    }
                }
            }

            if (! $sharesIdentity) {
                $this->replyTelegram(
                    $telegramChatId,
                    "🛑 <b>Alerta de Seguridad</b>\n\nTu Telegram ya está vinculado a otra cuenta y nuestros sistemas no detectan que seas el titular de esta nueva empresa.\n\nPor normativa de Protección de Datos, no se permite vincular empresas de distintos titulares en un mismo dispositivo.\n\nSi crees que es un error, contacta con tu asesor."
                );
                return;
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $link,
            $cliente,
            $telegramChatId,
            $updateId,
            $telegramMessageId,
            $update,
            $isRelink,
            $telegramUsername,
            $telegramFirstName
        ) {
            // PREPARAMOS MENSAJES CON NOMBRE DE EMPRESA Y ETIQUETAS HTML <b>
            $nombreAsesor = $cliente->asesor ? $cliente->asesor->name : 'nuestro equipo';
            $nombreEmpresa = $cliente->razon_social;
            
            $mensajeBienvenida = "🎉 <b>¡Vinculación completada con éxito!</b>\n\n"
                . "🏢 <b>Empresa:</b> {$nombreEmpresa}\n"
                . "👤 <b>Tu Asesor:</b> {$nombreAsesor}\n\n"
                . "A partir de ahora, este será nuestro canal directo para hablar y resolver cualquier duda o consulta que tengas de forma ágil.\n\n"
                . "📎 <b>IMPORTANTE:</b> Por motivos de seguridad y para que no se pierda nada, <b>el envío de facturas y documentos debe hacerse exclusivamente desde tu área privada</b>.\n\n"
                . "💻 Puedes acceder a tu portal siguiendo las instrucciones que recibiste por email para el acceso. Si tienes dudas o problemas, dímelo por aquí y te ayudaré.\n\n"
                . "¿En qué te puedo ayudar hoy?";

            // A) Ya existe
            $existingForCliente = ChatConversacion::query()
                ->where('tipo', 'cliente')
                ->where('cliente_id', $cliente->id)
                ->where('telegram_chat_id', $telegramChatId)
                ->first();

            if ($existingForCliente) {
                if (! $link->used_at) {
                    $link->used_at = now();
                    $link->save();
                }

                $existingForCliente->update([
                    'estado'              => 'abierta',
                    'asesor_id'           => $cliente->asesor_id,
                    'telegram_username'   => ltrim($telegramUsername, '@'),
                    'telegram_first_name' => $telegramFirstName,
                    'updated_at'          => now(),
                ]);

                $this->ensureTopicsIfMultiEmpresa($telegramChatId);
                $this->closeGeneralBestEffort($telegramChatId);
                
                $threadId = $existingForCliente->fresh()->telegram_thread_id;

                $this->insertSystemMessage(
                    chatId: (int) $existingForCliente->id,
                    contenido: 'El cliente ha vuelto a pinchar el enlace de vinculación.',
                    telegramMessageId: $telegramMessageId,
                    updateId: null,
                    update: $update
                );

                $this->replyTelegram($telegramChatId, "✅ Ya estabas vinculado a <b>{$nombreEmpresa}</b>. ¡Seguimos por aquí!", $threadId);
                return;
            }

            // B) Relink
            $existingOtherTelegram = ChatConversacion::query()
                ->where('tipo', 'cliente')
                ->where('cliente_id', $cliente->id)
                ->whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', $telegramChatId)
                ->first();

            if ($existingOtherTelegram && ! $isRelink) {
                $this->insertSystemMessage(
                    chatId: (int) $existingOtherTelegram->id,
                    contenido: 'Vinculación NO aplicada: este cliente ya está vinculado a otro Telegram.',
                    telegramMessageId: $telegramMessageId,
                    updateId: null,
                    update: $update
                );

                $this->replyTelegram($telegramChatId, '❌ Este cliente ya está vinculado a otro Telegram. Ignoro este enlace.');
                return;
            }

            if ($existingOtherTelegram && $isRelink) {
                $oldTelegramChatId = (int) $existingOtherTelegram->telegram_chat_id;
                if ($oldTelegramChatId > 0 && $oldTelegramChatId !== $telegramChatId) {
                    $this->replyTelegram(
                        $oldTelegramChatId,
                        "🔌 Tu cuenta de <b>{$nombreEmpresa}</b> se ha desvinculado de AsesorFy. Si necesitas volver a vincular, pide un nuevo enlace a tu asesor."
                    );
                }

                $existingOtherTelegram->update([
                    'telegram_chat_id'    => $telegramChatId,
                    'telegram_username'   => ltrim($telegramUsername, '@'),
                    'telegram_first_name' => $telegramFirstName,
                    'asesor_id'           => $cliente->asesor_id,
                    'estado'              => 'abierta',
                    'last_message_at'     => now(),
                ]);

                $this->ensureTopicsIfMultiEmpresa($telegramChatId);
                $this->closeGeneralBestEffort($telegramChatId);

                $threadId = $existingOtherTelegram->fresh()->telegram_thread_id;

                $this->insertSystemMessage(
                    chatId: (int) $existingOtherTelegram->id,
                    contenido: 'Re-vinculación aplicada ✅ (se actualizó el Telegram del cliente).',
                    telegramMessageId: $telegramMessageId,
                    updateId: null,
                    update: $update
                );

                $link->used_at = now();
                $link->save();

                $this->replyTelegram($telegramChatId, $mensajeBienvenida, $threadId);
                return;
            }

            if ($link->used_at) {
                $this->replyTelegram($telegramChatId, '❌ Este enlace ya fue usado. Pide uno nuevo a tu asesor.');
                return;
            }

            // D) Primera vinculación
            $existingNoTelegram = ChatConversacion::query()
                ->where('tipo', 'cliente')
                ->where('cliente_id', $cliente->id)
                ->whereNull('telegram_chat_id')
                ->first();

            if ($existingNoTelegram) {
                $existingNoTelegram->update([
                    'telegram_chat_id'    => $telegramChatId,
                    'telegram_username'   => ltrim($telegramUsername, '@'),
                    'telegram_first_name' => $telegramFirstName,
                    'asesor_id'           => $cliente->asesor_id,
                    'estado'              => 'abierta',
                    'last_message_at'     => now(),
                ]);

                $conv = $existingNoTelegram;
            } else {
                // E) Crear nueva conversación
                $conv = ChatConversacion::create([
                    'tipo'                => 'cliente',
                    'cliente_id'          => $cliente->id,
                    'asesor_id'           => $cliente->asesor_id,
                    'telegram_chat_id'    => $telegramChatId,
                    'telegram_username'   => ltrim($telegramUsername, '@'),
                    'telegram_first_name' => $telegramFirstName,
                    'estado'              => 'abierta',
                    'unread_count'        => 0,
                    'last_message_at'     => now(),
                ]);
            }

            // F) Asegurar topics SOLO si toca
            $this->ensureTopicsIfMultiEmpresa($telegramChatId);
            $this->closeGeneralBestEffort($telegramChatId);

            $threadId = $conv->fresh()->telegram_thread_id;

            $this->insertSystemMessage(
                chatId: (int) $conv->id,
                contenido: 'Conversación vinculada correctamente ✅',
                telegramMessageId: $telegramMessageId,
                updateId: $updateId,
                update: $update
            );

            $link->used_at = now();
            $link->save();

            $this->replyTelegram($telegramChatId, $mensajeBienvenida, $threadId);
        });
    }

    private function insertSystemMessage(
        int $chatId,
        string $contenido,
        int $telegramMessageId,
        ?int $updateId,
        array $update
    ): void {
        ChatMensaje::create([
            'chat_id'             => $chatId,
            'origen'              => 'sistema',
            'tipo'                => 'text',
            'contenido'           => $contenido,
            'telegram_message_id' => $telegramMessageId ?: null,
            'telegram_update_id'  => $updateId ?: null,
            'payload'             => json_encode($update),
            'leido'               => true,
        ]);
    }

    private function replyTelegram(int $telegramChatId, string $text, ?int $messageThreadId = null): void
    {
        try {
            // Aseguramos de que el servicio mande HTML en el formato
            app(TelegramService::class)->sendMessage($telegramChatId, $text, $messageThreadId);
        } catch (\Throwable $e) {
            // silent fail
        }
    }

    private function closeGeneralBestEffort(int $telegramChatId): void
    {
        try {
            app(TelegramService::class)->closeGeneralForumTopic($telegramChatId);
        } catch (\Throwable $e) {
            // silent
        }
    }

    private function ensureTopicsIfMultiEmpresa(int $telegramChatId): void
    {
        $convs = ChatConversacion::query()
            ->where('telegram_chat_id', $telegramChatId)
            ->get();

        if ($convs->count() > 1) {
            foreach ($convs as $conv) {
                $conv->ensureTopicForCliente();
            }
        }
    }
}