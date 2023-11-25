<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailRequest;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessSensibleWebhook extends ProcessWebhookJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'sensible';

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;

        $email = EmailRequest::whereId($payload['payload'])->sole();
        $email->sensible_extraction_uuid = $payload['id'];
        $email->sensible_output = $payload;
        $email->save();

        ProcessSensibleOutput::dispatch($email);
    }
}
