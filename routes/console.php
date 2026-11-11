<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwalkan backup database harian otomatis setiap pukul 02:00 WIB
Schedule::command('db:backup')->dailyAt('02:00')->timezone('Asia/Jakarta');
