<?php

declare(strict_types=1);

namespace App\Nova;

use App\Nova\Actions\CreateFundingAllocationLinesFromJacketPages;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for funding allocations.
 *
 * @extends \App\Nova\Resource<\App\Models\FundingAllocation>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class FundingAllocation extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\FundingAllocation::class;

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
        'fiscal_year.ending_year',
        'sga_bill_number',
        'type',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make('Fiscal Year')
                ->sortable()
                ->rules('required'),

            Select::make('Type')
                ->sortable()
                ->options(\App\Models\FundingAllocation::$types)
                ->displayUsingLabels(),

            Text::make('SGA Bill Number')
                ->sortable()
                ->nullable()
                ->rules(
                    'required_if:type,sga_bill',
                    'prohibited_unless:type,sga_bill',
                    'regex:/^\\d{2}J\\d{3}$/',
                    'nullable'
                ),

            HasMany::make('Funding Allocation Lines'),

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
        return [
            new CreateFundingAllocationLinesFromJacketPages(),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return $this->fundingAllocationLines()->count().' Lines | $'.
            number_format($this->fundingAllocationLines()->sum('amount'), 2);
    }
}
