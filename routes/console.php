<?php

declare(strict_types=1);

use App\Jobs\SyncEngagePurchaseRequests;
use Illuminate\Support\Facades\Schedule;
use UKFast\HealthCheck\Commands\CacheSchedulerRunning;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command(CacheSchedulerRunning::class)->everyMinute();
Schedule::job(new SyncEngagePurchaseRequests())
    ->timezone('America/New_York')
    ->dailyAt('06:00')
    ->environments('production');
