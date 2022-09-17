<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocuSignEnvelope;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSensibleOutput implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const UPN_REGEX = '/(?P<uid>[a-z]+[0-9]+)@gatech\.edu/';

    /**
     * Validation errors encountered while processing this Sensible output.
     *
     * @var array<string>
     */
    private array $validation_errors = [];

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly DocuSignEnvelope $envelope)
    {
        $this->queue = 'sensible';
    }

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function handle(): void
    {
        $envelope = $this->envelope;
        $sensible = $envelope->sensible_output;

        $envelope->type = $sensible['configuration'];

        $envelope->supplier_name = $envelope->type === 'vendor_payment' ?
            $this->getValueOrAddValidationError('vendor_name') :
            null;

        $envelope->description = $this->getValueOrAddValidationError('description');

        $envelope->amount = $this->getValueOrAddValidationError('total_amount');

        if ($envelope->type === 'purchase_reimbursement' || $envelope->type === 'travel_reimbursement') {
            $email = $this->getValueOrAddValidationError('payee_email_address');

            if ($email !== null) {
                if (str_ends_with($email, 'gatech.edu')) {
                    try {
                        $envelope->pay_to_user_id = User::whereEmail($email)->sole()->id;
                    } catch (ModelNotFoundException) {
                        $matches = [];

                        if (preg_match(self::UPN_REGEX, $email, $matches) === 1) {
                            try {
                                $envelope->pay_to_user_id = User::whereUsername($matches['uid'])->sole()->id;
                            } catch (ModelNotFoundException) {
                                $this->validation_errors[] =
                                    'Could not determine user to associate with reimbursement.';
                            }
                        }
                    }
                }
            }
        }

        $signed_at_string = $this->getValueOrAddValidationError('officer_signed_at');

        if ($signed_at_string !== null) {
            $envelope->submitted_at = self::parseDateTimeFromDocuSignFormat($signed_at_string);
        }

        if ($envelope->submitted_at === null) {
            if ($signed_at_string !== null) {
                $this->validation_errors[] = 'Sensible returned a submission timestamp, but it could not be parsed.';
            }
        } else {
            try {
                $envelope->fiscal_year_id = FiscalYear::fromDate($envelope->submitted_at)->id;
            } catch (ModelNotFoundException) {
                $this->validation_errors[] = 'This fiscal year is not yet created in Loop. Create it at '
                    .route('nova.pages.create', ['resource' => 'fiscal-years']);
            }
        }

        $envelope->save();

        Log::info(self::class.' '.json_encode($this->validation_errors));
    }

    private static function parseDateTimeFromDocuSignFormat(string $timestamp): ?CarbonImmutable
    {
        $datetime = CarbonImmutable::createFromFormat('!m/d/Y \| h:i a e', $timestamp);

        return $datetime === false ? null : $datetime;
    }

    /**
     * Get a field value from Sensible or add a validation error to the array.
     *
     * @param  string  $field_name
     * @return string|float|null
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    private function getValueOrAddValidationError(string $field_name): string|float|null
    {
        $fields = $this->envelope->sensible_output['parsed_document'];
        if (array_key_exists($field_name, $fields) && $fields[$field_name] !== null) {
            return $fields[$field_name]['value'];
        } else {
            if (! array_key_exists($field_name, $fields)) {
                $this->validation_errors[] = 'Sensible did not return a \''.$field_name.'\' field.';
            } elseif ($fields[$field_name] === null) {
                $this->validation_errors[] = 'Sensible could not extract the \''.$field_name.'\' field.';
            }
        }

        return null;
    }
}
