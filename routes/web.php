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
use App\Http\Controllers\ChatMensajeFileController;
use App\Http\Controllers\Portal\PortalFacturaPdfController;
use App\Http\Controllers\Portal\PortalFacturaPdfDownloadController;
use App\Http\Controllers\Portal\StripeBillingPortalController;



//eliminar
use Illuminate\Support\Facades\Mail;
use App\Mail\CorreoDePrueba;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->acceso_app) {
            return redirect('/admin');
        }
        return redirect('/portal');
    }
    return redirect('/portal/login');
})->name('home');

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

        Route::post('{token}/payment-options', [LeadConversionController::class, 'paymentOptions'])
            ->name('payment-options');

        Route::get('{token}/pago-inicial', [LeadConversionController::class, 'pagoInicial'])
            ->name('pago-inicial');

        Route::post('{token}/pago-inicial', [LeadConversionController::class, 'guardarPagoInicial'])
            ->name('pago-inicial.store');

        Route::get('{token}/pago-recurrente', [LeadConversionController::class, 'pagoRecurrente'])
            ->name('pago-recurrente');

        Route::post('{token}/pago-recurrente', [LeadConversionController::class, 'guardarPagoRecurrente'])
            ->name('guardar-pago-recurrente');


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


Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/chat-mensajes/{chatMensaje}/file', ChatMensajeFileController::class)
        ->name('chat-mensajes.file');
});

//portal
// Página de acceso bloqueado (ANTES del middleware de verificación)
Route::get('/portal/bloqueado', function () {
    return view('portal.bloqueado');
})->name('portal.bloqueado')->middleware('auth');




// Contrato de responsabilidad
use App\Http\Controllers\Public\ContratoResponsabilidadController;

Route::prefix('responsabilidad')->name('responsabilidad.')->group(function () {
    Route::get('{token}', [ContratoResponsabilidadController::class, 'show'])
        ->name('show');
    Route::post('{token}/firmar', [ContratoResponsabilidadController::class, 'firmar'])
        ->name('firmar');
    Route::get('{token}/firmado', [ContratoResponsabilidadController::class, 'firmado'])
        ->name('firmado');
});

// Activación de cuenta por link mágico
use App\Http\Controllers\Portal\ActivacionController;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/activar/{token}', [ActivacionController::class, 'show'])->name('activate');
    Route::post('/activar', [ActivacionController::class, 'store'])->name('activate.store');
    Route::get('/enlace-expirado', fn() => view('portal.activacion.expired'))->name('activate.expired');
});

// Rutas del portal de facturas
Route::middleware(['auth'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/facturas/pdf/{factura}', PortalFacturaPdfController::class)->name('facturas.pdf');
    Route::get('/facturas/download/{factura}', PortalFacturaPdfDownloadController::class)->name('facturas.download');
    Route::get('/stripe/billing-portal', StripeBillingPortalController::class)->name('stripe.billing-portal');
});

require __DIR__.'/auth.php';
