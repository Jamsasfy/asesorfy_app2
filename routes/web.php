<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\FacturaPdfController; // <-- Asegúrate de que esta importación esté
use App\Http\Controllers\FileViewController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Public\LeadConversionController;

use App\Http\Controllers\Public\StripePaymentController;
use App\Http\Controllers\Public\PaymentLinkController;



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


    
// --- GRUPO DE CONVERSIÓN DE LEADS ---
Route::prefix('conversion')->name('conversion.')->group(function () {

    // 1. Rutas que requieren que el link sea VÁLIDO (no usado y no caducado)
    Route::middleware('conversion.link.valid')->group(function () {
        // GET /conversion/{token} (Muestra formulario)
        Route::get('{token}', [LeadConversionController::class, 'show'])->name('show')->whereUuid('token');
        
        // POST /conversion/{token} (Guarda datos y va a contrato)
        Route::post('{token}', [LeadConversionController::class, 'submit'])->name('submit')->whereUuid('token');

        // GET /conversion/{token}/contrato (Muestra contrato scrollable)
        Route::get('{token}/contrato', [LeadConversionController::class, 'contract'])->name('contract')->whereUuid('token');

        // POST /conversion/{token}/firmar (Firma, genera PDF y marca como usado)
        Route::post('{token}/firmar', [LeadConversionController::class, 'sign'])->name('sign')->whereUuid('token');
    });

    // 2. Ruta de agradecimiento (Permite acceso aunque el link esté usado o caducado, para ver el resultado final)
    Route::get('{token}/gracias', [LeadConversionController::class, 'thankyou'])->name('thanks')->whereUuid('token');
});
// rutas publicas stripe
Route::prefix('pagos')->name('payment.')->group(function () {
    Route::get('{factura}/pagar', [StripePaymentController::class, 'pay'])->name('pay');
    Route::get('{factura}/ok', [StripePaymentController::class, 'success'])->name('success');
    Route::get('{factura}/ko', [StripePaymentController::class, 'cancel'])->name('cancel');
});

//ruta de boton enviar enlace pro email
Route::middleware(['auth'])->group(function () {
    Route::post('/admin/facturas/{factura}/enviar-enlace-pago', [PaymentLinkController::class, 'send'])
        ->name('payment.send-link');
});


require __DIR__.'/auth.php';
