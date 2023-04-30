<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Nova;

use App\Nova\Actions\ProcessSensibleOutput;
use App\Nova\Actions\RunSensibleExtraction;
use App\Nova\Actions\SyncDocuSignEnvelopeToQuickBooks;
use App\Nova\Actions\UploadDocuSignEnvelope;
use App\Nova\Lenses\ReimbursementsMissingExpenseReports;
use App\Nova\Lenses\ReimbursementsMissingInvoices;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for DocuSign envelopes.
 *
 * @extends \App\Nova\Resource<\App\Models\DocuSignEnvelope>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class DocuSignEnvelope extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\DocuSignEnvelope::class;

    /**
     * Get the displayble label of the resource.
     */
    public static function label(): string
    {
        return 'DocuSign Envelopes';
    }

    /**
     * Get the displayble singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return 'DocuSign Envelope';
    }

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return 'docusign-envelopes';
    }

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'envelope_id',
        'type',
        'description',
        'supplier_name',
        'amount',
    ];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'SOFO';

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->sortable(),

            Text::make('DocuSign Envelope UUID', 'envelope_uuid')
                ->onlyOnDetail(),

            BelongsTo::make('Fiscal Year')
                ->sortable(),

            Select::make('Form Type', 'type')
                ->sortable()
                ->options(\App\Models\DocuSignEnvelope::$types)
                ->displayUsingLabels()
                ->filterable(),

            BelongsTo::make('Pay To', 'payToUser', User::class)
                ->sortable()
                ->nullable(),

            Text::make('Supplier Name')
                ->sortable(),

            Text::make('Description')
                ->sortable(),

            Currency::make('Amount')
                ->sortable(),

            Currency::make(
                'Funding Sources Total',
                fn (): string|int => $this->fundingSources()->sum('docusign_funding_sources.amount')
            )
                ->onlyOnDetail(),

            BelongsTo::make('Workday Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable()
                ->searchable(),

            URL::make('QuickBooks Invoice', 'quickbooks_invoice_url')
                ->displayUsing(fn (): ?int => $this->quickbooks_invoice_document_number)
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenUpdating(),

            Number::make('QuickBooks Invoice ID', 'quickbooks_invoice_id')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks')),

            Number::make('QuickBooks Invoice Document Number', 'quickbooks_invoice_document_number')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks')),

            BelongsToMany::make('Funding Sources', 'fundingSources', FundingAllocationLine::class)
                ->fields(new DocuSignFundingSourceFields()),

            Panel::make('Documents', [
                File::make('SOFO Form', 'sofo_form_filename')
                    ->disk('local'),

                File::make('Summary', 'summary_filename')
                    ->disk('local'),
            ]),

            MorphMany::make('Attachments', 'attachments', Attachment::class),

            Panel::make('Sensible', [
                URL::make('View in Sensible', 'sensible_extraction_url')
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),

                Text::make('Extraction ID', 'sensible_extraction_uuid')
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),

                Code::make('Output', 'sensible_output')
                    ->json()
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),
            ]),

            Panel::make('Tracking', [
                BelongsTo::make('Replaces Envelope', 'replacesEnvelope', self::class)
                    ->hideFromIndex()
                    ->nullable()
                    ->searchable(),

                BelongsTo::make('Duplicate of Envelope', 'duplicateOf', self::class)
                    ->hideFromIndex()
                    ->nullable()
                    ->searchable(),

                Boolean::make('Lost')
                    ->hideFromIndex()
                    ->filterable(),

                Boolean::make('Internal Cost Transfer')
                    ->hideFromIndex()
                    ->filterable(),

                Boolean::make('Submission Error')
                    ->hideFromIndex()
                    ->filterable(),
            ]),

            HasMany::make('Replaced By', 'replacedBy', self::class),

            HasMany::make('Duplicates', 'duplicates', self::class),

            Panel::make('Timestamps', [
                DateTime::make('Submitted', 'submitted_at')
                    ->hideWhenUpdating()
                    ->sortable()
                    ->filterable(),

                DateTime::make('Created', 'created_at')
                    ->onlyOnDetail(),

                DateTime::make('Last Updated', 'updated_at')
                    ->onlyOnDetail(),

                DateTime::make('Deleted', 'deleted_at')
                    ->onlyOnDetail(),
            ]),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<\Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<\Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<\Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            ReimbursementsMissingExpenseReports::make(),
            ReimbursementsMissingInvoices::make(),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            ProcessSensibleOutput::make(),
            RunSensibleExtraction::make(),
            SyncDocuSignEnvelopeToQuickBooks::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('access-quickbooks'))
                ->canRun(
                    static fn (
                        NovaRequest $request,
                        \App\Models\DocuSignEnvelope $envelope
                    ): bool => $request->user()->can('access-quickbooks') &&
                        $request->user()->quickbooks_access_token !== null &&
                        ($envelope->type === 'purchase_reimbursement' || $envelope->type === 'travel_reimbursement') &&
                        $envelope->quickbooks_invoice_id === null &&
                        $envelope->pay_to_user_id === null &&
                        $envelope->submission_error === false &&
                        $envelope->internal_cost_transfer === false &&
                        $envelope->replacedBy()->count() === 0 &&
                        $envelope->duplicate_of_docusign_envelope_id === null
                ),
            UploadDocuSignEnvelope::make()
                ->canSee(
                    static fn (NovaRequest $request): bool => $request->user()->can('access-sensible')
                )
                ->canRun(
                    static fn (NovaRequest $request, \App\Models\DocuSignEnvelope $envelope): bool => $request
                        ->user()
                        ->can('access-sensible')
                ),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return \App\Models\DocuSignEnvelope::$types[$this->type]
            .' | '.$this->submitted_at?->format('Y-m-d')
            .($this->supplier_name === null ? '' : ' | '.$this->supplier_name)
            .' | '.$this->description
            .' | $'.number_format(abs($this->amount), 2);
    }
}
