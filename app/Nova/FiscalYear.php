<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for fiscal years.
 *
 * @extends \App\Nova\Resource<\App\Models\FiscalYear>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class FiscalYear extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\FiscalYear::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'ending_year';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'ending_year',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Ending Year')
                ->rules('required', 'integer', 'digits:4', 'min:2010', 'max:2030')
                ->creationRules('unique:fiscal_years,ending_year')
                ->updateRules('unique:fiscal_years,ending_year,{{resourceId}}')
                ->default(
                    static fn (Request $request): ?string => self::queryParamFromReferrer($request, 'ending_year')
                ),

            HasMany::make('Funding Allocations'),

            HasMany::make('DocuSign Envelopes', 'envelopes'),

            new Panel(
                'Timestamps',
                [
                    DateTime::make('Created', 'created_at')
                        ->onlyOnDetail(),

                    DateTime::make('Last Updated', 'updated_at')
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
}
