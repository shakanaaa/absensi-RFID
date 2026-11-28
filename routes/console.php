<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkronkan libur nasional setiap hari — memastikan tanggal libur
// tahun berjalan selalu terisi tanpa perlu generate manual.
Schedule::command('holidays:sync')->daily();
