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

    public function sendMessage(int|string $chatId, string $text): array
    {
        $response = $this->http()
            ->asJson()
            ->post($this->apiUrl('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

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
    public function sendPhoto(int|string $chatId, string $absolutePath, ?string $caption = null): array
    {
        $absolutePath = trim($absolutePath);

        if ($absolutePath === '' || ! is_file($absolutePath)) {
            throw new \RuntimeException("File not found: {$absolutePath}");
        }

        $filename = basename($absolutePath);

        $payload = [
            'chat_id' => $chatId,
        ];

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
    public function sendDocument(int|string $chatId, string $absolutePath, string $filename, ?string $caption = null): array
    {
        $absolutePath = trim($absolutePath);

        if ($absolutePath === '' || ! is_file($absolutePath)) {
            throw new \RuntimeException("File not found: {$absolutePath}");
        }

        $finalName = trim($filename) !== '' ? $filename : basename($absolutePath);

        $payload = [
            'chat_id' => $chatId,
        ];

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
