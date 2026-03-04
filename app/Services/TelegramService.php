<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramService
{
    private function token(): string
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            throw new \RuntimeException('Telegram bot token missing (services.telegram.bot_token).');
        }

        return $token;
    }

    private function apiUrl(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token()}/{$method}";
    }

    private function fileBaseUrl(string $filePath): string
    {
        // file_path viene de getFile, ej: "photos/file_123.jpg"
        return "https://api.telegram.org/file/bot{$this->token()}/{$filePath}";
    }

    private function http(): PendingRequest
    {
        // ✅ En local: evita cURL SSL si tu PHP no tiene CA bundle
        // ✅ En prod: verifica SSL (lo correcto)
        $verify = config('services.telegram.verify_ssl');

        if ($verify === null) {
            $verify = ! app()->environment('local');
        }

        $http = Http::timeout(25);

        if (! $verify) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    public function sendMessage(int|string $chatId, string $text, ?int $messageThreadId = null): array
    {
         
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }

        $response = $this->http()
            ->asJson()
            ->post($this->apiUrl('sendMessage'), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram sendMessage failed ({$response->status()}): {$response->body()}"
            );
        }

        return (array) $response->json();
    }

    /**
     * Enviar foto (imagen) a Telegram.
     * Recibe RUTA ABSOLUTA del fichero.
     */
    public function sendPhoto(int|string $chatId, string $absolutePath, ?string $caption = null, ?int $messageThreadId = null): array
    {
        $absolutePath = trim($absolutePath);

        if ($absolutePath === '' || ! is_file($absolutePath)) {
            throw new \RuntimeException("File not found: {$absolutePath}");
        }

        $filename = basename($absolutePath);

        $payload = [
            'chat_id' => $chatId,
        ];

        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }

        if (trim((string) $caption) !== '') {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        $response = $this->http()
            ->asMultipart()
            ->attach('photo', file_get_contents($absolutePath), $filename)
            ->post($this->apiUrl('sendPhoto'), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram sendPhoto failed ({$response->status()}): {$response->body()}"
            );
        }

        return (array) $response->json();
    }

    /**
     * Enviar documento (PDF, docx, etc.) a Telegram.
     * Recibe RUTA ABSOLUTA del fichero.
     */
    public function sendDocument(int|string $chatId, string $absolutePath, string $filename, ?string $caption = null, ?int $messageThreadId = null): array
    {
        $absolutePath = trim($absolutePath);

        if ($absolutePath === '' || ! is_file($absolutePath)) {
            throw new \RuntimeException("File not found: {$absolutePath}");
        }

        $finalName = trim($filename) !== '' ? $filename : basename($absolutePath);

        $payload = [
            'chat_id' => $chatId,
        ];

        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }

        if (trim((string) $caption) !== '') {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        $response = $this->http()
            ->asMultipart()
            ->attach('document', file_get_contents($absolutePath), $finalName)
            ->post($this->apiUrl('sendDocument'), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram sendDocument failed ({$response->status()}): {$response->body()}"
            );
        }

        return (array) $response->json();
    }

    /**
     * Crear un topic (hilo) en un chat privado con el bot.
     * Requiere Threaded Mode activado en @BotFather.
     *
     * @param int|string $chatId  ID del chat privado
     * @param string     $name    Nombre del topic (ej: "📋 MiSL S.L.")
     * @return int                El message_thread_id del topic creado
     */
    public function createForumTopic(int|string $chatId, string $name): int
    {
        $response = $this->http()
            ->asJson()
            ->post($this->apiUrl('createForumTopic'), [
                'chat_id' => $chatId,
                'name'    => $name,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram createForumTopic failed ({$response->status()}): {$response->body()}"
            );
        }

        $threadId = data_get($response->json(), 'result.message_thread_id');

        if (! $threadId) {
            throw new \RuntimeException(
                "Telegram createForumTopic: no message_thread_id in response: {$response->body()}"
            );
        }

        return (int) $threadId;
    }

    /**
     * Cerrar el topic "General" (All) de un chat con Topics activado.
     * Impide que el usuario escriba en él, obligándole a usar los hilos.
     */
    public function closeGeneralForumTopic(int|string $chatId): void
    {
        $response = $this->http()
            ->asJson()
            ->post($this->apiUrl('closeGeneralForumTopic'), [
                'chat_id' => $chatId,
            ]);

        // No lanzamos excepción: si falla (ya cerrado, no soportado, etc.) no es crítico
        if (! $response->successful()) {
            \Illuminate\Support\Facades\Log::warning('closeGeneralForumTopic failed', [
                'chat_id' => $chatId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }
    }

    /**
     * Obtener metadata de un archivo de Telegram por file_id.
     * Devuelve JSON con file_path, file_size, etc.
     */
    public function getFile(string $fileId): array
    {
        $response = $this->http()
            ->asJson()
            ->post($this->apiUrl('getFile'), [
                'file_id' => $fileId,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram getFile failed ({$response->status()}): {$response->body()}"
            );
        }

        return (array) $response->json();
    }

    /**
     * Descargar un archivo desde Telegram (file_path) y guardarlo en storage local.
     *
     * @param string $telegramFilePath Ej: "documents/file_123.pdf"
     * @param string $destRelativePath Ruta relativa dentro del disk "local" (storage/app)
     */
    public function downloadFileToStorage(string $telegramFilePath, string $destRelativePath): void
    {
        $url = $this->fileBaseUrl($telegramFilePath);

        $response = $this->http()->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Telegram file download failed ({$response->status()}): {$response->body()}"
            );
        }

        Storage::disk('local')->put($destRelativePath, $response->body());
    }
}