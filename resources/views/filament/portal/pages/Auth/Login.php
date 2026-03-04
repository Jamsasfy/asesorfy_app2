<?php

namespace App\Filament\Portal\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class Login extends BaseLogin
{
    protected function getFormActions(): array
    {
        return [
            Action::make('authenticate')
                ->label(__('filament-panels::pages/auth/login.form.actions.authenticate.label'))
                ->submit('authenticate'),
        ];
    }

    public function authenticate(): void
    {
        parent::authenticate();

        $user = Auth::user();

        if (!$user) return;

        $clientes = $user->clientes()->get();

        if ($clientes->count() === 1) {
            session(['cliente_activo_id' => $clientes->first()->id]);
        } elseif ($clientes->count() > 1) {
            session()->forget('cliente_activo_id');
        }
    }
}