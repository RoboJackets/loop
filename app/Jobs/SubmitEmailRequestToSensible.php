<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailRequest;
use App\Util\Sentry;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SubmitEmailRequestToSensible implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @psalm-mutation-free
     */
    public function __construct(private readonly EmailRequest $emailRequest)
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
                            'document_url' => URL::signedRoute(
                                'document.download',
                                ['email' => $this->emailRequest->id],
                                now()->addDay()
                            ),
                            'webhook' => [
                                'payload' => $this->emailRequest->id,
                                'url' => URL::signedRoute('webhook-client-sensible', [], now()->addDay()),
                            ],
                        ],
                    ]
                )->getBody()->getContents(),
                true
            )
        );

        $this->emailRequest->sensible_extraction_uuid = $json['id'];
        $this->emailRequest->save();
    }

    /**
     * The unique ID of the job.
     *
     * @psalm-mutation-free
     */
    public function uniqueId(): string
    {
        return strval($this->emailRequest->id);
    }
}
