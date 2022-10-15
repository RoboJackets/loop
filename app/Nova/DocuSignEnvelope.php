<?php

declare(strict_types=1);

namespace App\Nova;

use App\Nova\Actions\ProcessSensibleOutput;
use App\Nova\Actions\RunSensibleExtraction;
use App\Nova\Lenses\MissingExpenseReports;
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
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
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
                ->displayUsingLabels(),

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

            BelongsTo::make('Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable(),

            BelongsToMany::make('Funding Sources', 'fundingSources', FundingAllocationLine::class)
                ->fields(new DocuSignFundingSourceFields()),

            Panel::make('Documents', [
                ...($this->sofo_form_filename === null || $this->type === null ? [] : [
                    File::make(\App\Models\DocuSignEnvelope::$types[$this->type].' Form', 'sofo_form_filename')
                        ->disk('local'),
                ]),
                ...($this->summary_filename === null ? [] : [
                    File::make('Summary', 'summary_filename')
                        ->disk('local'),
                ]),
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
                    ->nullable(),

                Boolean::make('Lost')
                    ->onlyOnDetail(),
            ]),

            HasMany::make('Replaced By', 'replacedBy', self::class),

            Panel::make('Timestamps', [
                DateTime::make('Submitted', 'submitted_at')
                    ->onlyOnDetail(),

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
            MissingExpenseReports::make(),
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
        ];
    }
}
