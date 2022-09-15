<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocuSignEnvelope;
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

        $envelope = DocuSignEnvelope::whereEnvelopeUuid($payload['payload'])->sole();

        $envelope->sensible_extraction_uuid = $payload['id'];
        $envelope->sensible_output = $payload;
        $envelope->save();

        ProcessSensibleOutput::dispatch($envelope);
    }
}
