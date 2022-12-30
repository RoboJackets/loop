<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Models\Attachment;
use App\Models\User;
use App\Util\QuickBooks;
use App\Util\Sentry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use QuickBooksOnline\API\Data\IPPInvoice;
use QuickBooksOnline\API\Data\IPPReimburseCharge;
use QuickBooksOnline\API\Facades\Invoice;

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
    public $confirmText = 'Are you sure you want to sync this envelope to QuickBooks?';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\DocuSignEnvelope>  $models
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $user = User::whereId($fields->quickbooks_user_id)->sole();
        $data_service = QuickBooks::getDataService($user);
        $envelope = $models->sole();

        $reimburse_charge_response = Sentry::wrapWithChildSpan(
            'quickbooks.get_reimburse_charge',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPReimburseCharge => $data_service->FindById(
                'ReimburseCharge',
                $fields->quickbooks_reimburse_charge_id
            )
        );

        $invoice_response = Sentry::wrapWithChildSpan(
            'quickbooks.create_invoice',
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            static fn (): IPPInvoice => $data_service->Add(Invoice::create([
                'TxnDate' => $envelope->submitted_at->format('Y/m/d'),
                'CustomerRef' => [
                    'value' => config('quickbooks.invoice.customer_id'),
                ],
                'CurrencyRef' => [
                    'value' => 'USD',
                ],
                'Line' => [
                    [
                        'Amount' => $envelope->amount,
                        'Description' => $envelope->description,
                        'DetailType' => 'SalesItemLineDetail',
                        'SalesItemLineDetail' => [
                            'ItemRef' => [
                                'value' => config('quickbooks.invoice.item_id'),
                            ],
                            'ServiceDate' => $reimburse_charge_response->TxnDate,
                        ],
                        'LinkedTxn' => [
                            [
                                'TxnId' => $fields->quickbooks_reimburse_charge_id,
                                'TxnType' => 'ReimburseCharge',
                                'TxnLineId' => 1,
                            ],
                        ],
                    ],
                ],
            ]))
        );

        $envelope->quickbooks_invoice_id = $invoice_response->Id;
        $envelope->quickbooks_invoice_document_number = $invoice_response->DocNumber;
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

            Select::make('Billable Expense', 'quickbooks_reimburse_charge_id')
                ->options(
                    static fn (): array => Cache::remember(
                        'reimburse_charges',
                        30,
                        static fn (): array => collect(
                            Sentry::wrapWithChildSpan(
                                'quickbooks.query_reimburse_charges',
                                static fn (): array => QuickBooks::getDataService($request->user())
                                    ->Query('select * from ReimburseCharge where HasBeenInvoiced = false')
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
                ->help('Only expenses that are ready to invoice are shown.'),
        ];
    }
}
