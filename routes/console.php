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

Schedule::command('leads:enviar-recordatorios')->dailyAt('10:00');