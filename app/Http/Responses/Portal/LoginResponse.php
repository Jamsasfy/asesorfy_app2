<?php

namespace App\Http\Responses\Portal;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // Redirigir siempre al portal después del login
        return redirect()->intended('/portal');
    }
}
