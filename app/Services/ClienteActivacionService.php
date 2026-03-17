<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Comentario;
use App\Mail\ClienteActivadoMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ClienteActivacionService
{
    /**
     * Activa un cliente: crea su usuario de acceso portal con link mágico.
     *
     * @param Cliente $cliente
     * @param string|null $context Contexto del trigger (ej: 'pago_completado', 'conversion_manual')
     * @return array ['success' => bool, 'user' => User|null, 'message' => string]
     */
    public function activarCliente(Cliente $cliente, ?string $context = null): array
    {
        try {
            // 1. Verificar si ya tiene usuario de acceso
            $usuarioExistente = $cliente->usuarios()->first();

            if ($usuarioExistente) {
                Log::info("Cliente {$cliente->id} ya tiene usuario de acceso portal", [
                    'user_id' => $usuarioExistente->id,
                    'context' => $context,
                ]);

                return [
                    'success' => false,
                    'user' => $usuarioExistente,
                    'message' => 'El cliente ya tiene usuario de acceso',
                ];
            }

            DB::beginTransaction();

            // 2. Crear usuario SIN contraseña definitiva (password aleatorio temporal)
            $user = User::create([
                'name' => $cliente->razon_social ?? $cliente->nombre . ' ' . $cliente->apellidos,
                'email' => $cliente->email_contacto,
                'password' => Hash::make(Str::random(32)), // Password temporal aleatorio
                'acceso_app' => false,
                'portal_activo' => true,
                'email_bienvenida_enviado' => false,
                'activation_token' => Str::random(64),
                'activation_token_expires_at' => Carbon::now()->addHours(72),
            ]);

            // 3. Vincular usuario con cliente
            $cliente->usuarios()->attach($user->id);

            Log::info("✅ Usuario portal creado para cliente {$cliente->id}", [
                'user_id' => $user->id,
                'cliente_id' => $cliente->id,
                'email' => $user->email,
                'context' => $context,
            ]);

            // 4. Registrar comentario en el cliente
            Comentario::create([
                'comentable_type' => Cliente::class,
                'comentable_id' => $cliente->id,
                'user_id' => 9999, // Bot IA Fy
                'contenido' => "🔑 Usuario de acceso al portal creado automáticamente. Email: {$user->email}",
            ]);

            // 5. Enviar email con link mágico de activación
            $this->enviarEmailActivacion($cliente, $user, $context);

            // 6. Marcar email como enviado
            $user->update(['email_bienvenida_enviado' => true]);

            DB::commit();

            return [
                'success' => true,
                'user' => $user,
                'message' => 'Cliente activado correctamente. Email con link de activación enviado.',
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error("❌ Error activando cliente {$cliente->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => $context,
            ]);

            return [
                'success' => false,
                'user' => null,
                'message' => 'Error al activar cliente: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envía el email de activación con link mágico.
     */
    private function enviarEmailActivacion(Cliente $cliente, User $user, ?string $context): void
    {
        try {
            $asesor = $cliente->asesor;
            $suscripcionPrincipal = $cliente->tarifa_principal_activa;

            Mail::to($cliente->email_contacto)
                ->send(new ClienteActivadoMail(
                    cliente: $cliente,
                    user: $user,
                    asesor: $asesor,
                    suscripcion: $suscripcionPrincipal
                ));

            // Registrar en comentarios
            Comentario::create([
                'comentable_type' => Cliente::class,
                'comentable_id' => $cliente->id,
                'user_id' => 9999,
                'contenido' => "📧 Email de activación enviado a {$cliente->email_contacto} con link mágico (válido 72h).",
            ]);

            Log::info("✅ Email activación enviado", [
                'cliente_id' => $cliente->id,
                'email' => $cliente->email_contacto,
                'context' => $context,
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ Error enviando email activación", [
                'cliente_id' => $cliente->id,
                'error' => $e->getMessage(),
            ]);
            // No lanzamos excepción, el usuario ya está creado
        }
    }
}
