<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Jobs;

use App\Models\EmailRequest;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessPostmarkInboundWebhook extends ProcessWebhookJob
{
    private const FILENAME_SANITIZATION_REGEX = '/(?P<filename>^[!a-zA-Z0-9() .\#-_,–]+$)/';

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
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $date = Carbon::parse($payload['Date']);

        collect($payload['Attachments'])->each(static function (array $value, int $key) use ($date): void {
            if (preg_match(self::FILENAME_SANITIZATION_REGEX, $value['Name']) !== 1) {
                throw new \Exception('Filename does not match regex');
            }

            $email = EmailRequest::create([
                'email_sent_at' => $date,
                'fiscal_year_id' => FiscalYear::fromDate($date)->id,
            ]);

            $disk_path = 'email/'.$email->id.'/'.$value['Name'];

            // @phan-suppress-next-line PhanPossiblyFalseTypeArgument
            Storage::disk('local')->put($disk_path, base64_decode($value['Content'], true));

            $email->vendor_document_filename = $disk_path;
            $email->save();

            SubmitEmailRequestToSensible::dispatch($email);
            GenerateThumbnail::dispatch(Storage::disk('local')->path($email->vendor_document_filename));
        });
    }
}
