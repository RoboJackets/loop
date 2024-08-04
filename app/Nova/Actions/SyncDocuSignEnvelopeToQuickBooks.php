<?php

declare(strict_types=1);

// phpcs:disable Generic.Commenting.DocComment.MissingShort
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

class SyncDocuSignEnvelopeToQuickBooks extends Action
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
    public $confirmText = 'Select the corresponding billable expense for this DocuSign envelope.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\DocuSignEnvelope>  $models
     *
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $data_service = QuickBooks::getDataService(Auth::user());
        $envelope = $models->sole();

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

        $invoice->TxnDate = $envelope->submitted_at->format('Y/m/d');
        $invoice->DueDate = $envelope->submitted_at->addDays(30)->format('Y/m/d');
        $invoice->CurrencyRef = $currency_ref;
        $invoice->DocNumber = $envelope->id;
        $invoice->PrivateNote = $reimburse_charge->PrivateNote.' | '.$envelope->description;

        /** @var \QuickBooksOnline\API\Data\IPPLine $line */
        foreach ($invoice->Line as $line) {
            if (
                // @phpstan-ignore-next-line
                $line->DetailType === 'SalesItemLineDetail' &&
                // @phpstan-ignore-next-line
                $line->LinkedTxn?->TxnType === 'ReimburseCharge' &&
                $line->LinkedTxn?->TxnId === $fields->quickbooks_reimburse_charge_id
            ) {
                $line->Description = $reimburse_charge->PrivateNote.' | '.$envelope->description;
                $line->SalesItemLineDetail->ItemRef = $item_ref;
                $line->SalesItemLineDetail->ServiceDate = $reimburse_charge->TxnDate;
            }
        }

        $invoice = Sentry::wrapWithChildSpan(
            'quickbooks.update_invoice',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPInvoice => $data_service->Update($invoice)
        );

        $envelope->quickbooks_invoice_id = $invoice->Id;
        $envelope->quickbooks_invoice_document_number = $invoice->DocNumber;
        $envelope->save();

        QuickBooks::uploadAttachmentToInvoice($data_service, $envelope, $envelope->sofo_form_filename);

        $envelope->attachments->each(
            static function (Attachment $attachment, int $key) use ($data_service, $envelope): void {
                QuickBooks::uploadAttachmentToInvoice($data_service, $envelope, $attachment->filename);
            }
        );

        return Action::openInNewTab($envelope->quickbooks_invoice_url);
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
                ->rules('required')
                ->searchable()
                ->help('Only expenses that have been invoiced and not matched are shown.'),
        ];
    }
}
