<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Jobs;

use App\Mail\EmailRequestProcessed;
use App\Models\EmailRequest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessSensibleOutput implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Validation errors encountered while processing this Sensible output.
     *
     * @var array<string>
     */
    private array $validation_errors = [];

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly EmailRequest $emailRequest)
    {
        $this->queue = 'sensible';
    }

    /**
     * Execute the job.
     *
     * @phan-suppress PhanPossiblyNullTypeArgumentInternal
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function handle(): void
    {
        $email = $this->emailRequest;

        if (
            ! array_key_exists('parsed_document', $email->sensible_output) ||
            ! array_key_exists('invoice', $email->sensible_output['parsed_document']) ||
            $email->sensible_output['parsed_document']['invoice'] === null
        ) {
            Mail::send(new EmailRequestProcessed($this->emailRequest, ['Sensible could not extract any fields']));

            return;
        }

        $email->vendor_name = $this->getValueOrAddValidationError('vendor_name');
        $email->vendor_document_amount = $this->getValueOrAddValidationError('invoice_total');
        $email->vendor_document_reference = $this->getValueOrAddValidationError('invoice_id');

        $invoice_date = $this->getValueOrAddValidationError('invoice_date');
        if ($invoice_date !== null) {
            $email->vendor_document_date = Carbon::parse($invoice_date);
        }

        $email->save();

        Mail::send(new EmailRequestProcessed($this->emailRequest, $this->validation_errors));
    }

    /**
     * Get a field value from Sensible or add a validation error to the array.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    private function getValueOrAddValidationError(string $field_name): string|float|int|null
    {
        $fields = $this->emailRequest->sensible_output['parsed_document']['invoice']['metadata'];
        if (array_key_exists($field_name, $fields) && $fields[$field_name] !== null) {
            return $fields[$field_name]['value'];
        } else {
            if (! array_key_exists($field_name, $fields)) {
                $this->validation_errors[] = 'Sensible did not return a \''.$field_name.'\' field';
            } elseif ($fields[$field_name] === null) {
                $this->validation_errors[] = 'Sensible could not extract the \''.$field_name.'\' field';
            }
        }

        return null;
    }
}
