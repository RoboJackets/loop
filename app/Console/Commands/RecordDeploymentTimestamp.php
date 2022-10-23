<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecordDeploymentTimestamp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'record-deployment-timestamp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record the current time as the most recent deployment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Cache::put('last_deployment', Carbon::now()->unix());

        return 0;
    }
}
