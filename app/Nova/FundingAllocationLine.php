<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for funding allocation lines.
 *
 * @extends \App\Nova\Resource<\App\Models\FundingAllocationLine>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class FundingAllocationLine extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\FundingAllocationLine::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'amount',
        'description',
        'line_number',
    ];

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make('Funding Allocation')
                ->withoutTrashed()
                ->searchable()
                ->sortable()
                ->rules('required')
                ->default(
                    static fn (Request $request): ?string => self::queryParamFromReferrer(
                        $request,
                        'funding_allocation_id'
                    )
                ),

            Number::make('Line Number')
                ->sortable()
                ->rules('required', 'integer', 'min:1', 'max:65535')
                ->default(
                    static fn (Request $request): ?string => self::queryParamFromReferrer($request, 'line_number')
                ),

            Text::make('Description')
                ->rules('required'),

            Currency::make('Line Amount', 'amount')
                ->sortable()
                ->rules('required'),

            Currency::make(
                'Spent Amount',
                fn (): string|int => $this->envelopes()->sum('docusign_funding_sources.amount')
            )
                ->onlyOnDetail(),

            BelongsToMany::make('DocuSign Envelopes', 'envelopes', DocuSignEnvelope::class)
                ->fields(new DocuSignFundingSourceFields()),

            new Panel(
                'Timestamps',
                [
                    DateTime::make('Created', 'created_at')
                        ->onlyOnDetail(),

                    DateTime::make('Last Updated', 'updated_at')
                        ->onlyOnDetail(),

                    DateTime::make('Deleted', 'deleted_at')
                        ->onlyOnDetail(),
                ]
            ),
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
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return $this->description.' | $'.number_format($this->amount, 2);
    }
}
