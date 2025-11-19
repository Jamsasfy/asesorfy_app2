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

// Línea temporal para test – elimínala o coméntala cuando todo funcione
Schedule::command('leads:enviar-informe-emails-diario')->everyMinute();

