<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Nova\Actions;

use App\Exceptions\CouldNotMatchEnvelope;
use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\ExpenseReportLine;
use App\Models\User;
use App\Util\QuickBooks;
use App\Util\Sentry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use QuickBooksOnline\API\Data\IPPPayment;
use QuickBooksOnline\API\Facades\Payment;

class SyncExpensePaymentToQuickBooks extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Sync to QuickBooks';

    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * The text to be used for the action's confirm button.
     *
     * @var string
     */
    public $confirmButtonText = 'Sync';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Are you sure you want to sync this payment to QuickBooks?';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\ExpensePayment>  $models
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $user = User::whereId($fields->quickbooks_user_id)->sole();
        $data_service = QuickBooks::getDataService($user);
        $payment = $models->sole();

        $lines = [];

        $envelopes_not_synced = DocuSignEnvelope::whereNull('quickbooks_invoice_id')
            ->whereHas(
                'expenseReport',
                static function (Builder $query) use ($payment): void {
                    $query->where('expense_payment_id', '=', $payment->workday_instance_id);
                }
            )
            ->count();

        if ($envelopes_not_synced > 0) {
            return Action::danger(
                $envelopes_not_synced.' '.($envelopes_not_synced === 1 ? 'envelope has' : 'envelopes have')
                .' not been synced to QuickBooks, and must be synced before this payment can sync'
            );
        }

        DocuSignEnvelope::whereHas(
            'expenseReport',
            static function (Builder $query) use ($payment): void {
                $query->where('expense_payment_id', '=', $payment->workday_instance_id);
            }
        )
            ->get()
            ->each(static function (DocuSignEnvelope $envelope, int $key) use (&$lines): void {
                if ($envelope->expenseReport->envelopes()->count() === 1) {
                    $lines[] = [
                        'Amount' => $envelope->expenseReport->amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $envelope->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } else {
                    $envelope_amounts_from_lines = [];

                    $envelope->expenseReport->lines->each(
                        static function (ExpenseReportLine $line, int $key) use (&$envelope_amounts_from_lines): void {
                            try {
                                $envelope_uuid = $line->attachments->map(
                                    static fn (Attachment $attachment, int $key): ?string => $attachment
                                        ->toSearchableArray()['docusign_envelope_uuid']
                                )->filter(
                                    static fn (?string $envelope_uuid, int $key): bool => $envelope_uuid !== null
                                )
                                    ->sole();
                            } catch (MultipleItemsFoundException|ItemNotFoundException $e) {
                                throw new CouldNotMatchEnvelope(
                                    'Could not match envelope for expense report line '.$line->id,
                                    0,
                                    $e
                                );
                            }

                            // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
                            if (array_key_exists($envelope_uuid, $envelope_amounts_from_lines)) {
                                $envelope_amounts_from_lines[$envelope_uuid] += $line->amount;
                            } else {
                                // @phan-suppress-next-line PhanTypeMismatchDimAssignment
                                $envelope_amounts_from_lines[$envelope_uuid] = $line->amount;
                            }
                        }
                    );

                    ray($envelope_amounts_from_lines);

                    $lines[] = [
                        'Amount' => $envelope_amounts_from_lines[$envelope->envelope_uuid],
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $envelope->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                }
            });

        $payment_response = Sentry::wrapWithChildSpan(
            'quickbooks.create_payment',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPPayment => $data_service->Add(Payment::create([
                'TotalAmt' => $payment->amount,
                'CustomerRef' => [
                    'value' => config('quickbooks.invoice.customer_id'),
                ],
                'CurrencyRef' => [
                    'value' => 'USD',
                ],
                'PaymentMethodRef' => [
                    'value' => config('quickbooks.payment_method_id'),
                ],
                'Line' => $lines,
                'TxnDate' => $payment->payment_date->format('Y/m/d'),
                'PaymentRefNum' => $payment->transaction_reference,
            ]))
        );

        $payment->quickbooks_payment_id = $payment_response->Id;
        $payment->save();

        return Action::openInNewTab($payment->quickbooks_payment_url);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     *
     * @phan-suppress PhanTypeInvalidCallableArraySize
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('User', 'quickbooks_user_id')
                ->options([strval($request->user()->id) => $request->user()->name])
                ->default(strval($request->user()->id))
                ->required()
                ->rules('required')
                ->withMeta(['extraAttributes' => ['readonly' => true]]),
        ];
    }
}
