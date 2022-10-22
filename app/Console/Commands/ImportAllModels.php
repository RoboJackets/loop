<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocuSignEnvelope;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Console\Command;

class ImportAllModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:import-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all supported models into the search index';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('scout:import', [
            'model' => User::class,
        ]);
        $this->call('scout:import', [
            'model' => DocuSignEnvelope::class,
        ]);
        $this->call('scout:import', [
            'model' => ExpenseReport::class,
        ]);

        return 0;
    }
}
