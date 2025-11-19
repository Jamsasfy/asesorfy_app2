<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\FacturaPdfController; // <-- Asegúrate de que esta importación esté
use App\Http\Controllers\FileViewController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;


//eliminar
use Illuminate\Support\Facades\Mail;
use App\Mail\CorreoDePrueba;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/facturas/generar-pdf/{factura}', [FacturaPdfController::class, 'generarPdf'])
    ->name('facturas.generar-pdf')
    ->middleware('auth');

    Route::get('/view-storage-file/{path}', [FileViewController::class, 'show'])
    ->where('path', '.*')
    ->name('file.view')
    ->middleware('auth'); // <-- AÑADIR ESTA LÍNEA


    Route::get('/cron/2a9f8b7c0d28b7af/schedule-run', function () {
    Log::info('HTTP CRON: entrando en /cron/.../schedule-run');

    Artisan::call('schedule:run');

    Log::info('HTTP CRON: schedule:run ejecutado', [
        'output' => Artisan::output(),
    ]);

    return 'Scheduler executed OK';
});


require __DIR__.'/auth.php';
