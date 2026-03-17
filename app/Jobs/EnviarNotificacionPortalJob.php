<?php

namespace App\Jobs;

use App\Models\NotificacionPortal;
use App\Models\User;
use App\Models\ChatConversacion;
use App\Mail\NotificacionPortalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnviarNotificacionPortalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public NotificacionPortal $notificacion
    ) {}

    public function handle(): void
    {
        // Obtener usuarios destinatarios
        $usuarios = $this->obtenerDestinatarios();

        $canales = $this->notificacion->canales ?? [];

        foreach ($usuarios as $usuario) {
            // Registrar el envío básico si no existe
            $yaRegistrado = $this->notificacion->vistas()
                ->where('user_id', $usuario->id)
                ->exists();

            if (!$yaRegistrado) {
                $this->notificacion->vistas()->attach($usuario->id, [
                    'visto_en_plataforma' => false,
                    'leido_at' => null,
                    'canal_recibido' => implode(',', $canales),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Enviar por cada canal y marcar
            if (in_array('email', $canales)) {
                $this->enviarEmail($usuario);
                // Marcar como enviado por email
                $this->notificacion->vistas()->updateExistingPivot($usuario->id, [
                    'enviado_email' => true,
                    'enviado_email_at' => now(),
                ]);
            }

            if (in_array('telegram', $canales)) {
                $this->enviarTelegram($usuario);
                // Marcar como enviado por telegram
                $this->notificacion->vistas()->updateExistingPivot($usuario->id, [
                    'enviado_telegram' => true,
                    'enviado_telegram_at' => now(),
                ]);
            }

            // 'plataforma' no requiere envío, solo el registro en BD
        }

        Log::info('Notificación portal enviada', [
            'notificacion_id' => $this->notificacion->id,
            'usuarios_count' => $usuarios->count(),
        ]);

        // Marcar como enviada
        $this->notificacion->update([
            'enviada' => true,
            'enviada_at' => now(),
        ]);
    }

    protected function obtenerDestinatarios()
    {
        $destinatarios = $this->notificacion->destinatarios;

        if ($destinatarios === 'todos') {
            // Todos los usuarios con acceso al portal
            return User::where('acceso_app', 0)
                ->where('portal_activo', 1)
                ->get();
        }

        if ($destinatarios === 'por_servicio') {
            $servicioIds = $this->notificacion->filtro_servicios ?? [];
            
            if (empty($servicioIds)) {
                return collect();
            }

            // Usuarios cuyos clientes tienen suscripciones activas a estos servicios
            return User::where('acceso_app', 0)
                ->where('portal_activo', 1)
                ->whereHas('clientes.suscripciones', function ($q) use ($servicioIds) {
                    $q->whereIn('servicio_id', $servicioIds)
                      ->where('estado', 'activa');
                })
                ->get();
        }

        if ($destinatarios === 'cliente_especifico') {
            $clienteIds = $this->notificacion->filtro_clientes ?? [];
            
            if (empty($clienteIds)) {
                return collect();
            }

            // Usuarios vinculados a estos clientes
            return User::where('acceso_app', 0)
                ->where('portal_activo', 1)
                ->whereHas('clientes', function ($q) use ($clienteIds) {
                    $q->whereIn('clientes.id', $clienteIds);
                })
                ->get();
        }

        return collect();
    }

    protected function enviarEmail(User $usuario): void
    {
        try {
            Mail::to($usuario->email)->send(
                new NotificacionPortalMail($this->notificacion, $usuario)
            );
        } catch (\Exception $e) {
            Log::error('Error enviando email notificación portal', [
                'notificacion_id' => $this->notificacion->id,
                'user_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function enviarTelegram(User $usuario): void
    {
        try {
            Log::info('🔵 INICIO enviarTelegram', [
                'user_id' => $usuario->id,
                'user_email' => $usuario->email,
            ]);

            $botToken = config('services.telegram.bot_token');

            if (!$botToken) {
                Log::error('❌ Token de Telegram NO configurado');
                return;
            }

            // Obtener clientes del usuario
            $clientes = $usuario->clientes;

            if ($clientes->isEmpty()) {
                Log::warning('⚠️ Usuario sin clientes vinculados', [
                    'user_id' => $usuario->id,
                ]);
                return;
            }

            Log::info('👥 Clientes del usuario', [
                'count' => $clientes->count(),
            ]);

            // Enviar a cada cliente del usuario
            foreach ($clientes as $cliente) {
                Log::info('🔍 Buscando chat para cliente', [
                    'cliente_id' => $cliente->id,
                    'cliente_nombre' => $cliente->razon_social ?? ($cliente->nombre . ' ' . $cliente->apellidos),
                ]);

                // Buscar chat del CLIENTE
                $chat = ChatConversacion::where('cliente_id', $cliente->id)
                    ->whereNotNull('telegram_chat_id')
                    ->first();

                if (!$chat) {
                    Log::warning('⚠️ Cliente sin chat', [
                        'cliente_id' => $cliente->id,
                    ]);
                    continue;
                }

                if (!$chat->telegram_chat_id) {
                    Log::warning('⚠️ Chat sin telegram_chat_id', [
                        'cliente_id' => $cliente->id,
                        'chat_id' => $chat->id,
                    ]);
                    continue;
                }

                $chatId = $chat->telegram_chat_id;

                Log::info('📱 Chat Telegram encontrado', [
                    'chat_id' => $chatId,
                    'cliente_id' => $cliente->id,
                ]);

                // Formatear mensaje
                $tipoEmoji = match($this->notificacion->tipo) {
                    'info' => '🔵',
                    'aviso' => '🟡',
                    'urgente' => '🟠',
                    'critico' => '🔴',
                    default => 'ℹ️',
                };

                $tipoTexto = match($this->notificacion->tipo) {
                    'info' => 'INFORMACIÓN',
                    'aviso' => 'AVISO',
                    'urgente' => 'URGENTE',
                    'critico' => '⚠️ CRÍTICO ⚠️',
                    default => 'NOTIFICACIÓN',
                };

                $mensaje = "{$tipoEmoji} <b>{$tipoTexto}</b>\n\n";
                $mensaje .= "<b>{$this->notificacion->titulo}</b>\n\n";
                $mensaje .= strip_tags($this->notificacion->mensaje);
                $mensaje .= "\n\n📱 <i>Revisa tu portal para más detalles</i>";

                Log::info('💬 Enviando a Telegram', [
                    'chat_id' => $chatId,
                ]);

                // Enviar mensaje
                $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                    'parse_mode' => 'HTML',
                ]);

                if ($response->successful()) {
                    Log::info('✅ Telegram enviado', [
                        'chat_id' => $chatId,
                        'cliente_id' => $cliente->id,
                    ]);
                } else {
                    Log::error('❌ Error API Telegram', [
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);
                }
            }

            Log::info('🏁 FIN enviarTelegram');

        } catch (\Exception $e) {
            Log::error('💥 EXCEPCIÓN en enviarTelegram', [
                'user_id' => $usuario->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
