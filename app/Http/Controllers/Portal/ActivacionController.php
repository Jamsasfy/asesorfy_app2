<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ActivacionController extends Controller
{
    public function show(Request $request, string $token)
    {
        $user = User::where('activation_token', $token)
            ->where('activation_token_expires_at', '>', Carbon::now())
            ->first();

        if (!$user) {
            return view('portal.activacion.expired');
        }

        return view('portal.activacion.create', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('activation_token', $request->token)
            ->where('activation_token_expires_at', '>', Carbon::now())
            ->first();

        if (!$user) {
            return redirect()->route('portal.activate.expired');
        }

        // Actualizar contraseña y marcar como activado
        $user->update([
            'password' => Hash::make($request->password),
            'cuenta_activada_at' => Carbon::now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ]);

        // Auto-login
        auth()->login($user);

        return redirect('/portal')->with('success', '¡Cuenta activada! Bienvenido a AsesorFy.');
    }
}
