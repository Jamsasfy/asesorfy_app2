<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\TelegramLink;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    if ($updateId && DB::table('chat_mensajes')->where('telegram_update_id', $updateId)->exists()) {
        return response()->json(['ok' => true]);
    }

    // 3) Telegram puede mandar distintos tipos. Para MVP usamos message o edited_message.
    $message = data_get($update, 'message') ?: data_get($update, 'edited_message');
    if (! $message) {
        return response()->json(['ok' => true]);
    }

    $telegramChatId = (int) data_get($message, 'chat.id');
    $telegramMessageId = (int) data_get($message, 'message_id');

    $text = trim((string) data_get($message, 'text', ''));
    $caption = trim((string) data_get($message, 'caption', ''));

    // (Opcional útil para futuro)
    $telegramUserId = (int) data_get($message, 'from.id');
    $telegramUsername = (string) data_get($message, 'from.username', '');

    // Textos estándar
    $txtAudioBlocked = "🛡️ Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite mensajes de audio. Por favor, envía tu consulta por texto o adjunta la documentación necesaria. Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy. 🔒";

    $txtVideoBlocked = "🔒 Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite vídeos.\n\nPor favor, envía tu consulta por texto o adjunta la documentación necesaria (PDF, Word o Excel). Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy.";

    $txtAnimBlocked = "🔒 Por motivos de protección de datos, seguridad y trazabilidad, este canal no admite GIFs, stickers ni elementos animados.\n\nPor favor, envía tu consulta por texto o adjunta la documentación necesaria. Si necesitas tratarlo por voz, solicita una llamada con tu asesor de AsesorFy.";

    // 3.5) Bloqueo: NO aceptamos audios (voice/audio)
    $hasVoice = (bool) data_get($message, 'voice');
    $hasAudio = (bool) data_get($message, 'audio');

    if ($hasVoice || $hasAudio) {

        // Auditoría (opcional): si el chat existe, guardamos mensaje sistema
        $chat = DB::table('chat_conversaciones')
            ->where('telegram_chat_id', $telegramChatId)
            ->first();

        if ($chat) {
            DB::table('chat_mensajes')->insert([
                'chat_id'             => $chat->id,
                'origen'              => 'sistema',
                'tipo'                => 'text',
                'contenido'           => 'El cliente intentó enviar un audio (bloqueado).',
                'telegram_message_id' => $telegramMessageId ?: null,
                'telegram_update_id'  => null,
                'payload'             => json_encode($update),
                'leido'               => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            DB::table('chat_conversaciones')->where('id', $chat->id)->update([
                'last_message_at' => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->replyTelegram($telegramChatId, $txtAudioBlocked);

        return response()->json(['ok' => true]);
    }

    // 3.6) Bloqueo: NO aceptamos vídeos (video / video_note / animation)
    $hasVideo = (bool) data_get($message, 'video');
    $hasVideoNote = (bool) data_get($message, 'video_note');
    $hasAnimation = (bool) data_get($message, 'animation'); // GIF también entra aquí

    if ($hasVideo || $hasVideoNote || $hasAnimation) {

        // Auditoría (opcional)
        $chat = DB::table('chat_conversaciones')
            ->where('telegram_chat_id', $telegramChatId)
            ->first();

        if ($chat) {
            DB::table('chat_mensajes')->insert([
                'chat_id'             => $chat->id,
                'origen'              => 'sistema',
                'tipo'                => 'text',
                'contenido'           => 'El cliente intentó enviar un vídeo/GIF (bloqueado).',
                'telegram_message_id' => $telegramMessageId ?: null,
                'telegram_update_id'  => null,
                'payload'             => json_encode($update),
                'leido'               => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            DB::table('chat_conversaciones')->where('id', $chat->id)->update([
                'last_message_at' => now(),
                'updated_at'      => now(),
            ]);
        }

        // Si es animation (GIF), usamos el texto de animaciones; si no, el de vídeo
        $this->replyTelegram($telegramChatId, $hasAnimation ? $txtAnimBlocked : $txtVideoBlocked);

        return response()->json(['ok' => true]);
    }

    // 3.7) Bloqueo: GIFs / stickers / animaciones (y emojis animados tipo Telegram Premium)
    $hasSticker = (bool) data_get($message, 'sticker');
    $hasDice = (bool) data_get($message, 'dice');

    $entities = (array) data_get($message, 'entities', []);
    $captionEntities = (array) data_get($message, 'caption_entities', []);

    $hasCustomEmoji = collect(array_merge($entities, $captionEntities))
        ->contains(fn ($e) => (string) data_get($e, 'type') === 'custom_emoji');

    if ($hasSticker || $hasDice || $hasCustomEmoji) {
        $this->replyTelegram($telegramChatId, $txtAnimBlocked);
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
            );
        } else {
            $this->replyTelegram($telegramChatId, 'Enlace inválido. Pide uno nuevo a tu asesor.');
        }

        return response()->json(['ok' => true]);
    }

    // 5) Localizar conversación por telegram_chat_id
    $chat = DB::table('chat_conversaciones')
        ->where('telegram_chat_id', $telegramChatId)
        ->first();

    if (! $chat) {
        $this->replyTelegram(
            $telegramChatId,
            'Esta cuenta de Telegram no está vinculada a AsesorFy. Usa el enlace más reciente o pide uno nuevo a tu asesor.'
        );

        return response()->json(['ok' => true]);
    }

    // 6) Validación de documentos (allowlist + tamaño) ANTES de guardar
    $document = data_get($message, 'document');
    if ($document) {
        $mime = (string) data_get($document, 'mime_type', '');
        $fileName = (string) data_get($document, 'file_name', '');
        $fileSize = (int) data_get($document, 'file_size', 0);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Si el "documento" realmente es un vídeo, lo bloqueamos SIEMPRE con el mensaje de vídeos
        $videoExt = ['mp4', 'mov', 'mkv', 'avi', 'webm', 'm4v'];
        $isVideoDoc = str_starts_with($mime, 'video/')
            || in_array($ext, $videoExt, true)
            || in_array($mime, ['application/x-matroska'], true);

        if ($isVideoDoc) {
            $this->replyTelegram($telegramChatId, $txtVideoBlocked);
            return response()->json(['ok' => true]);
        }

        // Máximo 25 MB
        $maxBytes = 25 * 1024 * 1024;

        if ($fileSize > $maxBytes) {
            $this->replyTelegram(
                $telegramChatId,
                "🔒 Archivo demasiado grande. El tamaño máximo permitido es 25 MB.\n\nPor favor, envía un archivo más ligero o divídelo en varios."
            );
            return response()->json(['ok' => true]);
        }

        // Allowlist MIME (sin ZIP)
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

        // Allowlist por extensión (por si Telegram no manda mime)
        $allowedExt = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt',
            'jpg', 'jpeg', 'png', 'webp',
        ];

        $mimeOk = ($mime !== '' && in_array($mime, $allowedMimes, true));
        $extOk = ($ext !== '' && in_array($ext, $allowedExt, true));

        if (! $mimeOk && ! $extOk) {
            $this->replyTelegram(
                $telegramChatId,
                "🔒 Formato no admitido por motivos de seguridad.\n\nFormatos permitidos: PDF, Word (DOC/DOCX), Excel (XLS/XLSX), TXT e imágenes (JPG/PNG/WEBP).\nTamaño máximo: 25 MB."
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

        $this->bumpChatCounters((int) $chat->id);
        return response()->json(['ok' => true]);
    }

    // --- B) FOTO (array de tamaños -> cogemos el último, suele ser el mayor) ---
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

            $this->bumpChatCounters((int) $chat->id);
            return response()->json(['ok' => true]);
        }
    }

    // --- C) TEXTO ---
    if ($text === '') {
        return response()->json(['ok' => true]);
    }

    DB::table('chat_mensajes')->insertOrIgnore([
        'chat_id'             => $chat->id,
        'origen'              => 'cliente',
        'tipo'                => 'text',
        'contenido'           => $text,
        'telegram_message_id' => $telegramMessageId ?: null,
        'telegram_update_id'  => $updateId ?: null,
        'payload'             => json_encode($update),
        'leido'               => false,
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $this->bumpChatCounters((int) $chat->id);

    return response()->json(['ok' => true]);
}


    private function bumpChatCounters(int $chatId): void
    {
        DB::table('chat_conversaciones')->where('id', $chatId)->update([
            'unread_count'    => DB::raw('unread_count + 1'),
            'last_message_at' => now(),
            'updated_at'      => now(),
        ]);
    }

    private function storeIncomingAttachment(
        int $chatId,
        string $tipo, // photo|document
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

        // 1) Preguntar a Telegram el file_path real
        $fileInfo = $tg->getFile($fileId);
        $filePathOnTelegram = (string) data_get($fileInfo, 'result.file_path', '');

        if ($filePathOnTelegram === '') {
            // Si Telegram no devuelve file_path, no rompemos webhook
            return;
        }

        $ext = pathinfo($filePathOnTelegram, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? $ext : ($tipo === 'photo' ? 'jpg' : 'bin');

        // 2) Inferir mime si no viene
        if ($mime === '') {
            $mime = match (strtolower($ext)) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };
        }

        // 3) Tamaño real (si Telegram lo da)
        $sizeFromTg = (int) data_get($fileInfo, 'result.file_size', 0);
        if ($sizeFromTg > 0) {
            $size = $sizeFromTg;
        }

        // 4) Guardar en storage/app/documentos/telegram/chats/{chatId}/YYYY/MM/{uuid}.{ext}
        $rel = 'documentos/telegram/chats/' . $chatId . '/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.' . $ext;

        // Descarga binario
        $tg->downloadFileToStorage($filePathOnTelegram, $rel);

        // 5) Guardar mensaje (nota: contenido se usa para preview en sidebar)
        $preview = trim($caption) !== ''
            ? trim($caption)
            : ($tipo === 'photo' ? '📷 Foto' : ('📎 ' . ($originalName ?: 'Documento')));

        DB::table('chat_mensajes')->insertOrIgnore([
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
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }

    private function handleStartToken(
        string $token,
        int $telegramChatId,
        ?int $updateId,
        int $telegramMessageId,
        array $update,
        int $telegramUserId = 0,
        string $telegramUsername = ''
    ): void {
        // ✅ RE-LINK: token especial permite sobrescribir SOLO si alguien con permisos lo envió
        $isRelink = str_starts_with($token, 'relink_');

        /** @var TelegramLink|null $link */
        $link = TelegramLink::where('token', $token)->first();
        if (! $link) {
            $this->replyTelegram($telegramChatId, 'Enlace inválido o caducado. Pide uno nuevo a tu asesor.');
            return;
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            $this->replyTelegram($telegramChatId, 'Este enlace ha caducado. Pide uno nuevo a tu asesor.');
            return;
        }

        /** @var Cliente|null $cliente */
        $cliente = Cliente::find($link->cliente_id);
        if (! $cliente) {
            $this->replyTelegram($telegramChatId, 'Este enlace no es válido. Pide uno nuevo a tu asesor.');
            return;
        }

        DB::transaction(function () use (
            $link,
            $cliente,
            $telegramChatId,
            $updateId,
            $telegramMessageId,
            $update,
            $isRelink
        ) {
            // A) Chat ya vinculado por ESTE telegram_chat_id
            $boundByTelegram = DB::table('chat_conversaciones')
                ->where('tipo', 'cliente')
                ->where('telegram_chat_id', $telegramChatId)
                ->first();

            if ($boundByTelegram) {
                if ((int) $boundByTelegram->cliente_id === (int) $cliente->id) {
                    if (! $link->used_at) {
                        $link->used_at = now();
                        $link->save();
                    }

                    DB::table('chat_conversaciones')->where('id', $boundByTelegram->id)->update([
                        'estado'     => 'abierta',
                        'updated_at' => now(),
                    ]);

                    $this->insertSystemMessage(
                        chatId: (int) $boundByTelegram->id,
                        contenido: 'Ya estabas vinculado ✅',
                        telegramMessageId: $telegramMessageId,
                        updateId: null,
                        update: $update
                    );

                    $this->replyTelegram($telegramChatId, 'Ya estabas vinculado ✅');
                    return;
                }

                $this->insertSystemMessage(
                    chatId: (int) $boundByTelegram->id,
                    contenido: 'Intento de vinculación ignorado: este Telegram ya está vinculado a otro cliente.',
                    telegramMessageId: $telegramMessageId,
                    updateId: null,
                    update: $update
                );

                $this->replyTelegram(
                    $telegramChatId,
                    'Este Telegram ya está vinculado a otro cliente. Ignoro este enlace. Si necesitas cambiar la cuenta, avisa a tu asesor.'
                );

                return;
            }

            // B) Chat del cliente
            $existing = DB::table('chat_conversaciones')
                ->where('cliente_id', $cliente->id)
                ->where('tipo', 'cliente')
                ->first();

            if ($existing && ! empty($existing->telegram_chat_id) && (int) $existing->telegram_chat_id !== $telegramChatId) {

                if (! $isRelink) {
                    $this->insertSystemMessage(
                        chatId: (int) $existing->id,
                        contenido: 'Vinculación NO aplicada: este cliente ya está vinculado a otro Telegram. (Evito sobrescribir).',
                        telegramMessageId: $telegramMessageId,
                        updateId: null,
                        update: $update
                    );

                    $this->replyTelegram($telegramChatId, 'Este cliente ya está vinculado a otro Telegram. Ignoro este enlace.');
                    return;
                }

                $oldTelegramChatId = (int) $existing->telegram_chat_id;
                if ($oldTelegramChatId > 0 && $oldTelegramChatId !== $telegramChatId) {
                    $this->replyTelegram(
                        $oldTelegramChatId,
                        'Tu cuenta se ha desvinculado de AsesorFy. Si necesitas volver a vincular, pide un nuevo enlace a tu asesor.'
                    );
                }

                DB::table('chat_conversaciones')->where('id', $existing->id)->update([
                    'telegram_chat_id' => $telegramChatId,
                    'asesor_id'        => $cliente->asesor_id,
                    'estado'           => 'abierta',
                    'last_message_at'  => now(),
                    'updated_at'       => now(),
                ]);

                $this->insertSystemMessage(
                    chatId: (int) $existing->id,
                    contenido: 'Re-vinculación aplicada ✅ (se actualizó el Telegram del cliente).',
                    telegramMessageId: $telegramMessageId,
                    updateId: null,
                    update: $update
                );

                $link->used_at = now();
                $link->save();

                $this->replyTelegram($telegramChatId, 'Re-vinculado correctamente ✅ Ya puedes escribir aquí.');
                return;
            }

            if ($link->used_at) {
                if ($existing) {
                    $this->insertSystemMessage(
                        chatId: (int) $existing->id,
                        contenido: 'Enlace ya usado. No se aplicó ninguna vinculación nueva.',
                        telegramMessageId: $telegramMessageId,
                        updateId: null,
                        update: $update
                    );
                }

                $this->replyTelegram($telegramChatId, 'Este enlace ya fue usado. Pide uno nuevo a tu asesor.');
                return;
            }

            if ($existing) {
                DB::table('chat_conversaciones')->where('id', $existing->id)->update([
                    'telegram_chat_id' => $telegramChatId,
                    'asesor_id'        => $cliente->asesor_id,
                    'estado'           => 'abierta',
                    'last_message_at'  => now(),
                    'updated_at'       => now(),
                ]);

                $chatId = (int) $existing->id;
            } else {
                $chatId = (int) DB::table('chat_conversaciones')->insertGetId([
                    'tipo'             => 'cliente',
                    'cliente_id'       => $cliente->id,
                    'asesor_id'        => $cliente->asesor_id,
                    'telegram_chat_id' => $telegramChatId,
                    'estado'           => 'abierta',
                    'unread_count'     => 0,
                    'last_message_at'  => now(),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $this->insertSystemMessage(
                chatId: $chatId,
                contenido: 'Conversación vinculada correctamente ✅',
                telegramMessageId: $telegramMessageId,
                updateId: $updateId,
                update: $update
            );

            $link->used_at = now();
            $link->save();

            $this->replyTelegram($telegramChatId, 'Vinculado correctamente ✅ Ya puedes escribir aquí.');
        });
    }

    private function insertSystemMessage(
        int $chatId,
        string $contenido,
        int $telegramMessageId,
        ?int $updateId,
        array $update
    ): void {
        DB::table('chat_mensajes')->insertOrIgnore([
            'chat_id'             => $chatId,
            'origen'              => 'sistema',
            'tipo'                => 'text',
            'contenido'           => $contenido,
            'telegram_message_id' => $telegramMessageId ?: null,
            'telegram_update_id'  => $updateId ?: null,
            'payload'             => json_encode($update),
            'leido'               => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    private function replyTelegram(int $telegramChatId, string $text): void
    {
        try {
            app(TelegramService::class)->sendMessage($telegramChatId, $text);
        } catch (\Throwable $e) {
            // silent fail (webhook no debe petar por esto)
        }
    }
}
