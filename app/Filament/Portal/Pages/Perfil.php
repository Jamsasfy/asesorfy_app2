<?php

namespace App\Filament\Portal\Pages;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Perfil extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Mi perfil';
    protected static ?string $title = 'Mi perfil';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.portal.pages.perfil';

    // Datos del formulario
    public ?array $passwordData = [];

    public function mount(): void
    {
        //
    }

    public function updatePassword(): void
    {
        // Validación manual
        $this->validate([
            'passwordData.current_password' => ['required', 'current_password'],
            'passwordData.password' => ['required', 'min:8', 'confirmed'],
            'passwordData.password_confirmation' => ['required'],
        ]);

        auth()->user()->update([
            'password' => Hash::make($this->passwordData['password']),
        ]);

        // Limpiar campos
        $this->passwordData = [];

        Notification::make()
            ->title('✅ Contraseña actualizada')
            ->success()
            ->send();
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/portal/login');
    }
}