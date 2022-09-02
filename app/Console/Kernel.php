<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @phan-suppress PhanUnusedProtectedMethodParameter
     */
    protected function schedule(Schedule $schedule): void
    {
        // nothing to do here, yet
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        include base_path('routes/console.php');
    }
}
