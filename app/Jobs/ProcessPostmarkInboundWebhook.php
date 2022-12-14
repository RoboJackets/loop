<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use Illuminate\Support\Facades\Storage;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessPostmarkInboundWebhook extends ProcessWebhookJob
{
    private const FILENAME_SANITIZATION_REGEX = '/(?P<filename>^[!a-zA-Z0-9() .\#-_]+$)/';

    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'postmark';

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     * @phan-suppress PhanPossiblyFalseTypeArgument
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $subject = $payload['Subject'];

        if ($subject === 'Test subject') {
            return;
        }

        if (str_starts_with($subject, 'Completed: ') || str_starts_with($subject, 'Fwd: Completed: ')) {
            /**
             * Type hint for static analyzers.
             *
             * @var array<string, array<array<string, string>>> $payload
             */
            $attachments = collect($payload['Attachments']);

            $summary_attachment = $attachments->sole(
                static fn (array $value, int $key): bool => $value['Name'] === 'Summary.pdf'
            );

            $sofo_attachment = $attachments->sole(
                static fn (array $value, int $key): bool => str_starts_with($value['Name'], 'SOFO')
                    && preg_match(self::FILENAME_SANITIZATION_REGEX, $value['Name']) === 1
            );

            $envelope_uuid = DocuSignEnvelope::getEnvelopeUuidFromSummaryPdf(
                base64_decode($summary_attachment['Content'], true)
            );

            $attachments->each(static function (array $value, int $key): void {
                if ($value['Name'] === 'Summary.pdf' || str_starts_with($value['Name'], 'SOFO')) {
                    return;
                }

                if (preg_match(self::FILENAME_SANITIZATION_REGEX, $value['Name']) !== 1) {
                    throw new \Exception('Filename does not match regex');
                }
            });

            if (DocuSignEnvelope::whereEnvelopeUuid($envelope_uuid)->exists()) {
                return;
            }

            $envelope = DocuSignEnvelope::create([
                'envelope_uuid' => $envelope_uuid,
                'sofo_form_filename' => 'docusign/'.$envelope_uuid.'/'.$sofo_attachment['Name'],
                'summary_filename' => 'docusign/'.$envelope_uuid.'/Summary.pdf',
            ]);

            Storage::makeDirectory('docusign/'.$envelope_uuid);

            Storage::disk('local')
                ->put(
                    'docusign/'.$envelope_uuid.'/Summary.pdf',
                    base64_decode($summary_attachment['Content'], true)
                );

            Storage::disk('local')
                ->put(
                    'docusign/'.$envelope_uuid.'/'.$sofo_attachment['Name'],
                    base64_decode($sofo_attachment['Content'], true)
                );

            $attachments->each(static function (array $value, int $key) use ($envelope): void {
                if ($value['Name'] === 'Summary.pdf' || str_starts_with($value['Name'], 'SOFO')) {
                    return;
                }

                if (preg_match(self::FILENAME_SANITIZATION_REGEX, $value['Name']) !== 1) {
                    throw new \Exception('Filename does not match regex');
                }

                $disk_path = 'docusign/'.$envelope->envelope_uuid.'/'.$value['Name'];

                // @phan-suppress-next-line PhanPossiblyFalseTypeArgument
                Storage::disk('local')->put($disk_path, base64_decode($value['Content'], true));

                Attachment::create([
                    'attachable_type' => $envelope->getMorphClass(),
                    'attachable_id' => $envelope->id,
                    'filename' => $disk_path,
                ]);
            });

            SubmitDocuSignEnvelopeToSensible::dispatch($envelope);
        } else {
            throw new \Exception('Unrecognized subject line');
        }
    }
}
