<?php

declare(strict_types=1);

// phpcs:disable Generic.Commenting.DocComment.MissingShort
// phpcs:disable Generic.Formatting.SpaceBeforeCast.NoSpace
// phpcs:disable SlevomatCodingStandard.PHP.RequireExplicitAssertion.RequiredExplicitAssertion

namespace App\Nova\Actions;

use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Util\QuickBooks;
use App\Util\Sentry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use QuickBooksOnline\API\Data\IPPInvoice;
use QuickBooksOnline\API\Data\IPPReferenceType;
use QuickBooksOnline\API\Data\IPPReimburseCharge;

class SyncEmailRequestToQuickBooks extends Action
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
    public $confirmText = 'Select the corresponding billable expense for this email request.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\EmailRequest>  $models
     *
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $data_service = QuickBooks::getDataService(Auth::user());
        $email_request = $models->sole();

        $reimburse_charge = Sentry::wrapWithChildSpan(
            'quickbooks.get_reimburse_charge',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPReimburseCharge => $data_service->FindById(
                'ReimburseCharge',
                $fields->quickbooks_reimburse_charge_id
            )
        );

        $invoice = Sentry::wrapWithChildSpan(
            'quickbooks.get_invoice',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPInvoice => $data_service->FindById(
                'Invoice',
                // @phan-suppress-next-line PhanUndeclaredClassProperty
                $reimburse_charge->LinkedTxn->TxnId
            )
        );

        $currency_ref = new IPPReferenceType();
        $currency_ref->value = 'USD';

        $item_ref = new IPPReferenceType();
        $item_ref->value = config('quickbooks.invoice.item_id');

        $invoice->TxnDate = $email_request->email_sent_at->format('Y/m/d');
        $invoice->DueDate = $email_request->email_sent_at->addDays(30)->format('Y/m/d');
        $invoice->CurrencyRef = $currency_ref;
        $invoice->DocNumber = 'ME'.str_pad((string) $email_request->id, 3, '0', STR_PAD_LEFT);
        $invoice->PrivateNote = $reimburse_charge->PrivateNote.' | '.$email_request->vendor_name.' | '.
            $email_request->vendor_document_reference;

        /** @var \QuickBooksOnline\API\Data\IPPLine $line */
        foreach ($invoice->Line as $line) {
            if (
                // @phpstan-ignore-next-line
                $line->DetailType === 'SalesItemLineDetail' &&
                // @phpstan-ignore-next-line
                $line->LinkedTxn?->TxnType === 'ReimburseCharge' &&
                $line->LinkedTxn?->TxnId === $fields->quickbooks_reimburse_charge_id
            ) {
                $line->Description = $reimburse_charge->PrivateNote.' | '.$email_request->vendor_name.' | '.
                    $email_request->vendor_document_reference;
                $line->SalesItemLineDetail->ItemRef = $item_ref;
                $line->SalesItemLineDetail->ServiceDate = $reimburse_charge->TxnDate;
            }
        }

        $invoice = Sentry::wrapWithChildSpan(
            'quickbooks.update_invoice',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPInvoice => $data_service->Update($invoice)
        );

        $email_request->quickbooks_invoice_id = $invoice->Id;
        $email_request->quickbooks_invoice_document_number = $invoice->DocNumber;
        $email_request->save();

        QuickBooks::uploadAttachmentToInvoice($data_service, $email_request, $email_request->vendor_document_filename);

        $email_request->attachments->each(
            static function (Attachment $attachment, int $key) use ($data_service, $email_request): void {
                QuickBooks::uploadAttachmentToInvoice($data_service, $email_request, $attachment->filename);
            }
        );

        return Action::openInNewTab($email_request->quickbooks_invoice_url);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        $docusignInvoiceIds = DocuSignEnvelope::selectRaw('distinct(quickbooks_invoice_id)')
            ->whereNotNull('quickbooks_invoice_id')
            ->get()
            ->pluck('quickbooks_invoice_id')
            ->uniqueStrict()
            ->toArray();

        $engageInvoiceIds = EngagePurchaseRequest::selectRaw('distinct(quickbooks_invoice_id)')
            ->whereNotNull('quickbooks_invoice_id')
            ->get()
            ->pluck('quickbooks_invoice_id')
            ->uniqueStrict()
            ->toArray();

        $emailInvoiceIds = EmailRequest::selectRaw('distinct(quickbooks_invoice_id)')
            ->whereNotNull('quickbooks_invoice_id')
            ->get()
            ->pluck('quickbooks_invoice_id')
            ->uniqueStrict()
            ->toArray();

        $allInvoiceIds = collect($docusignInvoiceIds)
            ->concat($engageInvoiceIds)
            ->concat($emailInvoiceIds)
            ->uniqueStrict()
            ->toArray();

        return [
            Select::make('Billable Expense', 'quickbooks_reimburse_charge_id')
                ->options(
                    static fn (): array => Cache::remember(
                        'reimburse_charges',
                        10,
                        static fn (): array => collect(
                            Sentry::wrapWithChildSpan(
                                'quickbooks.query_reimburse_charges',
                                static fn (): array => QuickBooks::getDataService($request->user())
                                    ->Query(
                                        'select * from ReimburseCharge where HasBeenInvoiced = true'
                                        .' and CustomerRef = \''.config('quickbooks.invoice.customer_id').'\''
                                    )
                            )
                        )
                            ->filter(
                                static fn (IPPReimburseCharge $item, int $key): bool => ! in_array(
                                    // @phan-suppress-next-line PhanUndeclaredClassProperty
                                    intval($item->LinkedTxn->TxnId),
                                    $allInvoiceIds,
                                    true
                                )
                            )
                            ->mapWithKeys(
                                static fn (IPPReimburseCharge $item, int $key): array => [
                                    $item->Id => (
                                        $item->TxnDate.' | $'.$item->Amount.
                                        ($item->PrivateNote === null ? '' : ' | '.$item->PrivateNote)
                                    ),
                                ]
                            )
                            ->toArray()
                    )
                )
                ->required()
                ->rules('required', static function ($attribute, $value, $fail) use ($request): void {
                    $data_service = QuickBooks::getDataService($request->user());

                    $reimburse_charge = Sentry::wrapWithChildSpan(
                        'quickbooks.get_reimburse_charge',
                        // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
                        static fn (): IPPReimburseCharge => $data_service->FindById(
                            'ReimburseCharge',
                            $value
                        )
                    );

                    $email_request = EmailRequest::whereId(
                        $request->resourceId ?? $request->resources
                    )->sole();

                    if (floatval($reimburse_charge->Amount) !== $email_request->vendor_document_amount) {
                        if (
                            $email_request->expenseReport !== null &&
                            floatval($reimburse_charge->Amount) === $email_request->expenseReport->amount
                        ) {
                            return;
                        }

                        $fail(
                            'Billable expense amount does not match the vendor document amount for this email '.
                            'request.'
                        );
                    }
                })
                ->searchable()
                ->help('Only expenses that have been invoiced and not matched are shown.'),
        ];
    }
}
