<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocuSignEnvelope;
use App\Util\Sentry;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SubmitDocuSignEnvelopeToSensible implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly DocuSignEnvelope $envelope)
    {
        $this->queue = 'sensible';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $json = Sentry::wrapWithChildSpan(
            'sensible.async_extraction',
            fn (): array => json_decode(
                (new Client(
                    [
                        'headers' => [
                            'User-Agent' => 'RoboJackets Loop on '.config('app.url'),
                            'Authorization' => 'Bearer '.config('services.sensible.token'),
                            'Accept' => 'application/json',
                        ],
                        'allow_redirects' => false,
                    ]
                ))->post(
                    config('services.sensible.url'),
                    [
                        'json' => [
                            'content_type' => 'application/pdf',
                            'document_url' => URL::signedRoute(
                                'document.download',
                                ['envelope' => $this->envelope],
                                now()->addDay()
                            ),
                            'webhook' => [
                                'payload' => $this->envelope->envelope_uuid,
                                'url' => URL::signedRoute('webhook-client-sensible', [], now()->addDay()),
                            ],
                        ],
                    ]
                )->getBody()->getContents(),
                true
            )
        );

        $this->envelope->sensible_extraction_uuid = $json['id'];
        $this->envelope->save();
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return strval($this->envelope->id);
    }
}
