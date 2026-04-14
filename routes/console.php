<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\Catalog\PublishCatalogOutboxJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwalkan pemrosesan outbox setiap menit
Schedule::job(new PublishCatalogOutboxJob())->everyMinute()->withoutOverlapping();
