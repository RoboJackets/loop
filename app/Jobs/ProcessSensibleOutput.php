<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Jobs;

use App\Mail\DocuSignEnvelopeProcessed;
use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\DocuSignFundingSource;
use App\Models\ExpenseReportLine;
use App\Models\FiscalYear;
use App\Models\FundingAllocation;
use App\Models\FundingAllocationLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Spatie\Permission\Models\Role;

class ProcessSensibleOutput implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const UPN_REGEX = '/(?P<uid>[a-z]+[0-9]+)@gatech\.edu/';

    private const PURCHASE_ORDER_NUMBER_REGEX = '/[A-Za-z]{2,3}-\d{4}-PO-\d+/';

    private const FUNDING_NUMBER_NAMES = ['one', 'two', 'three', 'four', 'five'];

    private const FUNDING_ALLOCATION_ROW_NUMBER = [
        'sga_budget' => 1,
        'sga_bill' => 2,
        'foundation' => 3,
        'agency' => 4,
    ];

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
     * @phan-suppress PhanPossiblyNullTypeArgumentInternal
     * @phan-suppress PhanTypeArraySuspiciousNull
     * @phan-suppress PhanTypeArraySuspiciousNullable
     * @phan-suppress PhanTypeInvalidDimOffset
     */
    public function handle(): void
    {
        $envelope = $this->envelope;
        $sensible = $envelope->sensible_output;

        $envelope->type = $sensible['configuration'];

        $envelope->supplier_name = $envelope->type === 'vendor_payment'
            ? $this->getValueOrAddValidationError('vendor_name')
            : null;

        $envelope->description = $this->getValueOrAddValidationError('description');

        if (preg_match(self::PURCHASE_ORDER_NUMBER_REGEX, $envelope->description) !== 1) {
            $this->validation_errors[] = 'Description does not include a RoboJackets purchase order number';
        }

        if ($this->anyFieldSet('funding_source_table')) {
            $column = $sensible['parsed_document']['funding_source_table']['columns'][10]['values'];
            $rows = count($column);

            $envelope->amount = $column[$rows - 1]['value'];
        } else {
            $envelope->amount = $this->getValueOrAddValidationError('total_amount');
        }

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
                                    'Could not determine user to associate with reimbursement';
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
                $this->validation_errors[] = 'Sensible returned a submission timestamp, but it could not be parsed';
            }
        } else {
            try {
                $envelope->fiscal_year_id = FiscalYear::fromDate($envelope->submitted_at)->id;
            } catch (ModelNotFoundException) {
                $this->validation_errors[] = 'Fiscal year '.FiscalYear::intFromDate($envelope->submitted_at)
                    .' does not exist in Loop. Create it at '
                    .route(
                        'nova.pages.create',
                        [
                            'resource' => \App\Nova\FiscalYear::uriKey(),
                            'ending_year' => FiscalYear::intFromDate($envelope->submitted_at),
                        ]
                    );
            }
        }

        $envelope->save();

        if ($envelope->type === 'travel_reimbursement') {
            $this->validation_errors[] = 'Loop cannot automatically attach funding sources for travel reimbursement '
                .'forms. Manually attach funding sources at '
                .route(
                    'nova.pages.detail',
                    [
                        'resource' => \App\Nova\DocuSignEnvelope::uriKey(),
                        'resourceId' => $envelope->id,
                    ]
                );
        } elseif ($envelope->fiscal_year_id !== null) {
            if ($this->anyFieldSet(
                'sga_budget_one_line_number',
                'sga_budget_one_amount',
                'sga_budget_two_line_number',
                'sga_budget_two_amount',
                'sga_budget_three_line_number',
                'sga_budget_three_amount'
            )) {
                $budget_funding_allocation = $this->getSgaBudgetFundingAllocation();

                if ($budget_funding_allocation !== null) {
                    $this->attachSgaFundingSources($budget_funding_allocation);
                }
            }

            if ($this->anyFieldSet(
                'sga_bill_one_line_number',
                'sga_bill_one_amount',
                'sga_bill_two_line_number',
                'sga_bill_two_amount',
                'sga_bill_three_line_number',
                'sga_bill_three_amount'
            )) {
                if ($this->anyFieldSet('sga_bill_number')) {
                    $bill_number = $this->getValueOrAddValidationError('sga_bill_number');

                    if ($bill_number !== null) {
                        $bill_funding_allocation = $this->getSgaBillFundingAllocation($bill_number);

                        if ($bill_funding_allocation !== null) {
                            $this->attachSgaFundingSources($bill_funding_allocation);
                        }
                    }
                } else {
                    $this->validation_errors[] = 'This form references SGA bill lines, but Sensible did not return a '
                        .'bill number';
                }
            }

            if ($this->anyFieldSet('funding_source_table')) {
                $table = $this->envelope->sensible_output['parsed_document']['funding_source_table']['columns'];

                if ($table[10]['values'][1] !== null && $table[10]['values'][1]['value'] > 0) {
                    $budget_funding_allocation = $this->getSgaBudgetFundingAllocation();

                    if ($budget_funding_allocation !== null) {
                        $this->attachSgaFundingSourcesFromFundingSourcesTable($budget_funding_allocation, $table);
                    }
                }

                if ($table[10]['values'][2] !== null && $table[10]['values'][2]['value'] > 0) {
                    $bill_number = $this->getValueOrAddValidationError('sga_bill_number_in_table');

                    if ($bill_number !== null) {
                        $bill_funding_allocation = $this->getSgaBillFundingAllocation($bill_number);

                        if ($bill_funding_allocation !== null) {
                            $this->attachSgaFundingSourcesFromFundingSourcesTable($bill_funding_allocation, $table);
                        }
                    }
                }
            }

            $this->attachSingleLineFundingSources('agency');
            $this->attachSingleLineFundingSources('foundation');

            if ($envelope->amount !== floatval($envelope->fundingSources()->sum('docusign_funding_sources.amount'))) {
                $this->validation_errors[] = 'Total amount does not match sum of funding sources';
            }
        }

        $officer_name = $this->getValueOrAddValidationError('officer_name')[0];

        try {
            $user = User::search($officer_name)
                ->whereIn('role_id', Role::all()->modelKeys())
                ->get()
                ->sole();
        } catch (ItemNotFoundException|MultipleItemsFoundException) {
            $user = null;
            $this->validation_errors[] = 'Could not determine signing officer, so they did not receive this email';
        }

        Mail::send(new DocuSignEnvelopeProcessed($this->envelope, $this->validation_errors, $user));

        try {
            $attachment = Attachment::whereFilename($this->envelope->sofo_form_filename)->sole();

            if ($attachment->attachable_type === ExpenseReportLine::getMorphClassStatic()) {
                MatchExpenseReport::dispatch($attachment->attachable->expenseReport);
            }
        } catch (ModelNotFoundException) {
            return;
        }
    }

    private static function parseDateTimeFromDocuSignFormat(string $timestamp): ?CarbonImmutable
    {
        $datetime = CarbonImmutable::createFromFormat('!m/d/Y \| h:i a e', $timestamp);

        return $datetime === false ? null : $datetime;
    }

    /**
     * Get a field value from Sensible or add a validation error to the array.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    private function getValueOrAddValidationError(string $field_name): string|float|int|array|null
    {
        $fields = $this->envelope->sensible_output['parsed_document'];
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

    /**
     * Check if any of the provided field names are present in the Sensible output.
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    private function anyFieldSet(string ...$field_names): bool
    {
        $fields = $this->envelope->sensible_output['parsed_document'];
        foreach ($field_names as $field_name) {
            if (array_key_exists($field_name, $fields) && $fields[$field_name] !== null) {
                return true;
            }
        }

        return false;
    }

    private function attachSgaFundingSources(FundingAllocation $allocation): void
    {
        foreach (self::FUNDING_NUMBER_NAMES as $funding_number_name) {
            $line_number_field_name = $allocation->type.'_'.$funding_number_name.'_line_number';
            $amount_field_name = $allocation->type.'_'.$funding_number_name.'_amount';

            if ($this->anyFieldSet($line_number_field_name, $amount_field_name)) {
                $line_number = $this->getValueOrAddValidationError($line_number_field_name);
                $amount = $this->getValueOrAddValidationError($amount_field_name);

                if ($line_number !== null && $amount !== null) {
                    $funding_allocation_line = null;

                    try {
                        $funding_allocation_line = FundingAllocationLine::whereFundingAllocationId($allocation->id)
                            ->whereLineNumber($line_number)
                            ->sole();
                    } catch (ModelNotFoundException) {
                        $this->validation_errors[] = 'This form references '.$allocation->type_display_name.
                            ($allocation->type === 'sga_bill' ? ' '.$allocation->sga_bill_number : '')
                            .' line '.$line_number
                            .', but this line number does not exist in Loop. View the funding allocation at '
                            .route(
                                'nova.pages.detail',
                                [
                                    'resource' => \App\Nova\FundingAllocation::uriKey(),
                                    'resourceId' => $allocation->id,
                                ]
                            );
                    }

                    if ($funding_allocation_line !== null) {
                        DocuSignFundingSource::updateOrCreate(
                            [
                                'docusign_envelope_id' => $this->envelope->id,
                                'funding_allocation_line_id' => $funding_allocation_line->id,
                            ],
                            [
                                'amount' => $amount,
                            ]
                        );
                    }
                }
            }
        }
    }

    private function attachSgaFundingSourcesFromFundingSourcesTable(FundingAllocation $allocation, array $table): void
    {
        for ($number = 0; $number < 5; $number++) {
            $line_number = $table[$number * 2]['values'][self::FUNDING_ALLOCATION_ROW_NUMBER[$allocation->type]];

            if ($line_number !== null) {
                $line_number = $line_number['value'];
            }

            $amount = $table[($number * 2) + 1]['values'][self::FUNDING_ALLOCATION_ROW_NUMBER[$allocation->type]];

            if ($amount !== null) {
                $amount = $amount['value'];
            }

            if ($line_number === null && $amount !== null) {
                $this->validation_errors[] = 'Sensible could not extract a line number for '.
                    $allocation->type_display_name.
                    ($allocation->type === 'sga_bill' ? ' '.$allocation->sga_bill_number : '');
            }

            if ($line_number !== null && $amount === null) {
                $this->validation_errors[] = 'Sensible could not extract an amount for '.
                    $allocation->type_display_name.
                    ($allocation->type === 'sga_bill' ? ' '.$allocation->sga_bill_number : '');
            }

            if ($line_number !== null && $amount !== null) {
                $funding_allocation_line = null;

                try {
                    $funding_allocation_line = FundingAllocationLine::whereFundingAllocationId($allocation->id)
                        ->whereLineNumber($line_number)
                        ->sole();
                } catch (ModelNotFoundException) {
                    $this->validation_errors[] = 'This form references '.$allocation->type_display_name.
                        ($allocation->type === 'sga_bill' ? ' '.$allocation->sga_bill_number : '')
                        .' line '.$line_number
                        .', but this line number does not exist in Loop. View the funding allocation at '
                        .route(
                            'nova.pages.detail',
                            [
                                'resource' => \App\Nova\FundingAllocation::uriKey(),
                                'resourceId' => $allocation->id,
                            ]
                        );
                }

                if ($funding_allocation_line !== null) {
                    DocuSignFundingSource::updateOrCreate(
                        [
                            'docusign_envelope_id' => $this->envelope->id,
                            'funding_allocation_line_id' => $funding_allocation_line->id,
                        ],
                        [
                            'amount' => $amount,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Attach funding sources that only have a single line (Foundation or Agency).
     *
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    private function attachSingleLineFundingSources(string $type): void
    {
        $display_name = FundingAllocation::$types[$type];
        $allocation_line = null;
        $amount = null;

        if ($this->anyFieldSet($type.'_amount')) {
            $allocation_line = $this->getFundingAllocationLine($type);

            if ($allocation_line !== null) {
                $amount = $this->getValueOrAddValidationError($type.'_amount');
            }
        }

        if ($this->anyFieldSet('funding_source_table')) {
            $col = $this->envelope->sensible_output['parsed_document']['funding_source_table']['columns'][10]['values'];

            if ($col[self::FUNDING_ALLOCATION_ROW_NUMBER[$type]] !== null) {
                $allocation_line = $this->getFundingAllocationLine($type);
                $amount = $col[self::FUNDING_ALLOCATION_ROW_NUMBER[$type]]['value'];
            }
        }

        if ($allocation_line !== null) {
            if ($amount !== null) {
                DocuSignFundingSource::updateOrCreate(
                    [
                        'docusign_envelope_id' => $this->envelope->id,
                        'funding_allocation_line_id' => $allocation_line->id,
                    ],
                    [
                        'amount' => $amount,
                    ]
                );
            } else {
                $this->validation_errors[] = 'Attempted to attach a funding source from '.$display_name
                    .', but the amount was null';
            }
        }
    }

    /**
     * Get a funding allocation line for a single-line funding allocation.
     *
     * @phan-suppress PhanTypeMismatchReturn
     */
    private function getFundingAllocationLine(string $type): ?FundingAllocationLine
    {
        $display_name = FundingAllocation::$types[$type];

        $funding_allocation = null;

        try {
            $funding_allocation = FundingAllocation::whereType($type)
                ->whereFiscalYearId($this->envelope->fiscal_year_id)
                ->sole();
        } catch (ModelNotFoundException) {
            $this->validation_errors[] = 'This form references the '.$display_name.' account, but the funding '
                .'allocation for this fiscal year does not exist in Loop. Create it at '
                .route(
                    'nova.pages.create',
                    [
                        'resource' => \App\Nova\FundingAllocation::uriKey(),
                        'type' => $type,
                        'fiscal_year_id' => $this->envelope->fiscal_year_id,
                    ]
                );
        }

        if ($funding_allocation !== null) {
            $allocation_line = null;

            try {
                $allocation_line = FundingAllocationLine::whereFundingAllocationId($funding_allocation->id)
                    ->sole();
            } catch (ModelNotFoundException) {
                $this->validation_errors[] = 'This form references the '.$display_name
                    .' account, but there are no lines under the funding allocation. Create one at '
                    .route(
                        'nova.pages.create',
                        [
                            'resource' => \App\Nova\FundingAllocationLine::uriKey(),
                            'funding_allocation_id' => $funding_allocation->id,
                            'line_number' => 1,
                        ]
                    );
            } catch (MultipleRecordsFoundException) {
                $this->validation_errors[] = 'This form references the '.$display_name
                    .' account, but there are multiple lines associated with the funding allocation within Loop,'
                    .' so one cannot be automatically attached.';
            }

            return $allocation_line;
        }

        return null;
    }

    /**
     * Get the SGA budget funding allocation for this envelope.
     *
     * @phan-suppress PhanTypeMismatchReturn
     */
    private function getSgaBudgetFundingAllocation(): ?FundingAllocation
    {
        try {
            return FundingAllocation::whereType('sga_budget')
                ->whereFiscalYearId($this->envelope->fiscal_year_id)
                ->sole();
        } catch (ModelNotFoundException) {
            $this->validation_errors[] = 'This form references SGA budget lines, but the SGA budget funding '
                .'allocation for this fiscal year does not exist in Loop. Create it at '
                .route(
                    'nova.pages.create',
                    [
                        'resource' => \App\Nova\FundingAllocation::uriKey(),
                        'fiscal_year_id' => $this->envelope->fiscal_year_id,
                        'type' => 'sga_budget',
                    ]
                );
        }

        return null;
    }

    /**
     * Get an SGA bill funding allocation.
     *
     * @phan-suppress PhanTypeMismatchReturn
     */
    private function getSgaBillFundingAllocation(string $bill_number): ?FundingAllocation
    {
        try {
            return FundingAllocation::whereType('sga_bill')
                ->whereSgaBillNumber($bill_number)
                ->sole();
        } catch (ModelNotFoundException) {
            $this->validation_errors[] = 'This form references SGA bill '.$bill_number
                .', but this bill does not exist in Loop. Create it at '
                .route(
                    'nova.pages.create',
                    [
                        'resource' => \App\Nova\FundingAllocation::uriKey(),
                        'type' => 'sga_bill',
                        'sga_bill_number' => $bill_number,
                    ]
                );
        }

        return null;
    }
}
