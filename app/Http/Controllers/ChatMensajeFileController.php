<?php

namespace App\Http\Controllers;

use App\Models\ChatMensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatMensajeFileController extends Controller
{
    public function __invoke(Request $request, ChatMensaje $chatMensaje)
    {
        // 1) Auth (panel)
        if (! Auth::check()) {
            abort(403);
        }

        // 2) Debe existir archivo
        if (! $chatMensaje->file_path) {
            abort(404);
        }

        // 3) Seguridad: el asesor solo puede ver archivos de sus chats
        // (Ajusta si tienes roles/coordinador; por ahora “simple y seguro”)
        $chat = $chatMensaje->chat; // requiere relación en ChatMensaje -> chat()
        if (! $chat || (int) $chat->asesor_id !== (int) Auth::id()) {
            abort(403);
        }

        // 4) Resolver path absoluto en disk local (storage/app)
        $disk = Storage::disk('local');

        if (! $disk->exists($chatMensaje->file_path)) {
            abort(404);
        }

        $absolutePath = $disk->path($chatMensaje->file_path);

        $download = $request->boolean('download', false);

        $filename = $chatMensaje->file_original_name
            ?: basename($absolutePath);

        $mime = $chatMensaje->file_mime
            ?: ($disk->mimeType($chatMensaje->file_path) ?: 'application/octet-stream');

        if ($download) {
            return response()->download($absolutePath, $filename, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
