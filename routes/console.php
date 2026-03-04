<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===============================
// SCHEDULER DE LEADS AUTOMÁTICOS
// ===============================

Schedule::command('leads:enviar-recordatorios')->dailyAt('08:00');
Schedule::command('leads:enviar-informe-emails-diario')->dailyAt('09:00');
Schedule::command('documentos:purge-rechazados --days=30')->dailyAt('03:10');

Schedule::command('asesorfy:recordatorio-pendientes-respuesta')
    ->dailyAt('10:00')
    ->timezone('Europe/Madrid');

// ===============================
// ✅ SINCRONIZACIÓN STRIPE
// ===============================

Schedule::command('stripe:sync')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('Europe/Madrid');