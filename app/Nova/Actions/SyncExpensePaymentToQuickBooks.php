<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Nova\Actions;

use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReportLine;
use App\Util\QuickBooks;
use App\Util\Sentry;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
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
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $data_service = QuickBooks::getDataService(Auth::user());
        $payment = $models->sole();

        $lines = [];

        $requests_not_synced = EngagePurchaseRequest::whereNull('quickbooks_invoice_id')
            ->whereHas(
                'expenseReport',
                static function (Builder $query) use ($payment): void {
                    $query->where('expense_payment_id', '=', $payment->workday_instance_id);
                }
            )
            ->count();

        if ($requests_not_synced > 0) {
            return Action::danger(
                $requests_not_synced.' '.($requests_not_synced === 1 ? 'request has' : 'requests have')
                .' not been synced to QuickBooks, and must be synced before this payment can sync'
            );
        }

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

        $emails_not_synced = EmailRequest::whereNull('quickbooks_invoice_id')
            ->whereHas(
                'expenseReport',
                static function (Builder $query) use ($payment): void {
                    $query->where('expense_payment_id', '=', $payment->workday_instance_id);
                }
            )
            ->count();

        if ($emails_not_synced > 0) {
            return Action::danger(
                $emails_not_synced.' '.($emails_not_synced === 1 ? 'email has' : 'emails have')
                .' not been synced to QuickBooks, and must be synced before this payment can sync'
            );
        }

        EngagePurchaseRequest::whereHas(
            'expenseReport',
            static function (Builder $query) use ($payment): void {
                $query->where('expense_payment_id', '=', $payment->workday_instance_id);
            }
        )
            ->get()
            ->each(static function (EngagePurchaseRequest $engagePurchaseRequest, int $key) use (&$lines): void {
                if ($engagePurchaseRequest->expenseReport->engagePurchaseRequests()->count() === 1) {
                    $lines[] = [
                        'Amount' => $engagePurchaseRequest->expenseReport->amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $engagePurchaseRequest->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } elseif (
                    floatval(
                        $engagePurchaseRequest->expenseReport->engagePurchaseRequests()->sum('approved_amount')
                    ) === $engagePurchaseRequest->expenseReport->amount
                ) {
                    $lines[] = [
                        'Amount' => $engagePurchaseRequest->approved_amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $engagePurchaseRequest->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } elseif (
                    floatval(
                        $engagePurchaseRequest->expenseReport->engagePurchaseRequests()->sum('submitted_amount')
                    ) === $engagePurchaseRequest->expenseReport->amount
                ) {
                    $lines[] = [
                        'Amount' => $engagePurchaseRequest->submitted_amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $engagePurchaseRequest->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } else {
                    throw new Exception(
                        'Expense report is matched to multiple Engage requests and unable to automatically determine'.
                        ' splits'
                    );
                }
            });

        DocuSignEnvelope::whereHas(
            'expenseReport',
            static function (Builder $query) use ($payment): void {
                $query->where('expense_payment_id', '=', $payment->workday_instance_id);
            }
        )
            ->with('expenseReport.lines.attachments')
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
                                throw new Exception(
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

        EmailRequest::whereHas(
            'expenseReport',
            static function (Builder $query) use ($payment): void {
                $query->where('expense_payment_id', '=', $payment->workday_instance_id);
            }
        )
            ->get()
            ->each(static function (EmailRequest $emailRequest, int $key) use (&$lines): void {
                if ($emailRequest->expenseReport->emailRequests()->count() === 1) {
                    $lines[] = [
                        'Amount' => $emailRequest->expenseReport->amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $emailRequest->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } elseif (
                    floatval(
                        $emailRequest->expenseReport->emailRequests()->sum('vendor_document_amount')
                    ) === $emailRequest->expenseReport->amount
                ) {
                    $lines[] = [
                        'Amount' => $emailRequest->vendor_document_amount,
                        'LinkedTxn' => [
                            [
                                'TxnType' => 'Invoice',
                                'TxnId' => $emailRequest->quickbooks_invoice_id,
                            ],
                        ],
                    ];
                } else {
                    throw new Exception(
                        'Expense report is matched to multiple email requests and unable to automatically determine'.
                        ' splits'
                    );
                }
            });

        $payment_response = Sentry::wrapWithChildSpan(
            'quickbooks.create_payment',
            static fn (): IPPPayment => $data_service->Add(Payment::create([
                'TotalAmt' => $payment->amount,
                'CustomerRef' => [
                    'value' => config('quickbooks.invoice.customer_id'),
                ],
                'CurrencyRef' => [
                    'value' => 'USD',
                ],
                'PaymentMethodRef' => [
                    'value' => config('quickbooks.payment.method_id'),
                ],
                'DepositToAccountRef' => [
                    'value' => config('quickbooks.payment.account_id'),
                ],
                'Line' => $lines,
                'TxnDate' => $payment->bankTransaction->transaction_posted_at->format('Y/m/d'),
                'PaymentRefNum' => $payment->transaction_reference,
            ]))
        );

        $payment->quickbooks_payment_id = $payment_response->Id;
        $payment->save();

        return Action::openInNewTab($payment->quickbooks_payment_url);
    }
}
