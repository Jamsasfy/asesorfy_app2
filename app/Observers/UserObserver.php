<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Verificar si portal_activo cambió de 1 a 0 (bloqueo)
        if ($user->isDirty('portal_activo') && $user->portal_activo == 0) {
            // Eliminar todas las sesiones activas del usuario
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            Log::info("🔒 Sesiones cerradas para usuario bloqueado: {$user->email}");
        }
    }
}
