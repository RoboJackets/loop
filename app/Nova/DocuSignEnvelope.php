<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Nova;

use App\Nova\Actions\SyncDocuSignEnvelopeToQuickBooks;
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
     * Get the displayable label of the resource.
     */
    #[\Override]
    public static function label(): string
    {
        return 'DocuSign Envelopes';
    }

    /**
     * Get the displayable singular label of the resource.
     */
    #[\Override]
    public static function singularLabel(): string
    {
        return 'DocuSign Envelope';
    }

    /**
     * Get the URI key for the resource.
     */
    #[\Override]
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
        'envelope_uuid',
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
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<string>
     */
    public static $with = [
        'fiscalYear',
        'payToUser',
        'expenseReport',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    #[\Override]
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
                    ->disk('local')
                    ->thumbnail(fn (): ?string => $this->sofo_form_thumbnail_url),

                File::make('Summary', 'summary_filename')
                    ->disk('local')
                    ->thumbnail(fn (): ?string => $this->summary_thumbnail_url),
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
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    #[\Override]
    public function actions(NovaRequest $request): array
    {
        $resourceId = $request->resourceId ?? $request->resources;
        $user = $request->user();

        if ($resourceId === null || $user === null || ! $user->can('access-quickbooks')) {
            return [];
        }

        $docuSignEnvelope = \App\Models\DocuSignEnvelope::whereId($resourceId)->withTrashed()->sole();

        if (
            $docuSignEnvelope->deleted_at !== null ||
            $docuSignEnvelope->quickbooks_invoice_id !== null ||
            $docuSignEnvelope->quickbooks_invoice_document_number !== null ||
            $docuSignEnvelope->expense_report_id === null
        ) {
            return [];
        }

        return [
            SyncDocuSignEnvelopeToQuickBooks::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('access-quickbooks'))
                ->canRun(
                    static fn (NovaRequest $request, \App\Models\DocuSignEnvelope $envelope): bool => $request
                        ->user()
                        ->can('access-quickbooks')
                ),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    #[\Override]
    public function subtitle(): string
    {
        return \App\Models\DocuSignEnvelope::$types[$this->type]
            .' | '.$this->submitted_at?->format('Y-m-d')
            .($this->supplier_name === null ? '' : ' | '.$this->supplier_name)
            .' | '.$this->description
            .' | $'.number_format(abs($this->amount), 2);
    }
}
