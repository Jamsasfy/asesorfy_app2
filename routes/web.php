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

use App\Http\Controllers\Public\StripeSetupController;

use App\Http\Controllers\Webhooks\StripeWebhookController;



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

Route::prefix('conversion')->name('conversion.')->group(function () {

    Route::middleware('conversion.link.valid')->group(function () {

        // 1) Mostrar formulario
        Route::get('{token}', [LeadConversionController::class, 'show'])
            ->name('show');

        // 2) Enviar formulario
        Route::post('{token}/submit', [LeadConversionController::class, 'submit'])
            ->name('submit');

        // 3) Mostrar contrato
        Route::get('{token}/contract', [LeadConversionController::class, 'contract'])
            ->name('contract');

        // 4) Firmar contrato
        Route::post('{token}/sign', [LeadConversionController::class, 'sign'])
            ->name('sign');

        // 5) Vista final
        Route::get('{token}/finished', [LeadConversionController::class, 'finished'])
            ->middleware('check.recurrent')
            ->name('finished');
    });  

});

 Route::get('/stripe/setup-card/{token}', [StripeSetupController::class, 'setupCard'])
    ->name('stripe.setup-card');

Route::post('/stripe/setup-card/{token}/process', [StripeSetupController::class, 'processCard'])
    ->name('stripe.process-card');

Route::get('/stripe/setup-sepa/{token}', [StripeSetupController::class, 'setupSepa'])
    ->name('stripe.setup-sepa');

Route::post('/stripe/setup-sepa/{token}/process', [StripeSetupController::class, 'processSepa'])
    ->name('stripe.process-sepa');


// rutas publicas stripe (ahora por VENTA)
Route::prefix('pagos')->name('payment.')->group(function () {
    Route::get('{venta}/pagar', [StripePaymentController::class, 'pay'])->name('pay');
    Route::get('{venta}/ok', [StripePaymentController::class, 'success'])->name('success');
    Route::get('{venta}/ko', [StripePaymentController::class, 'cancel'])->name('cancel');
});

//ruta de boton enviar enlace pro email (LEGACY por factura, lo dejo igual)
Route::middleware(['auth'])->group(function () {
    Route::post('/admin/facturas/{factura}/enviar-enlace-pago', [PaymentLinkController::class, 'send'])
        ->name('payment.send-link');
});



Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');


require __DIR__.'/auth.php';
