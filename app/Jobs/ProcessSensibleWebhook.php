<?php

declare(strict_types=1);

namespace App\Jobs;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessSensibleWebhook extends ProcessWebhookJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'sensible';

    public int $tries = 1;

    public function handle(): void
    {
        // pending SenseML design
    }
}
