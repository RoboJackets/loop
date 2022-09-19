<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
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

            Number::make('SOFO Line Number', 'sofo_line_number')
                ->sortable()
                ->rules('nullable', 'integer', 'min:1', 'max:65535')
                ->help('Required for SGA Budget funding allocations'),

            Text::make('Description')
                ->rules('required'),

            Select::make('Type')
                ->sortable()
                ->options(\App\Models\FundingAllocationLine::$types)
                ->displayUsingLabels()
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

    /**
     * Handle any post-validation processing.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    protected static function afterValidation(NovaRequest $request, $validator): void
    {
        $funding_allocation_type = \App\Models\FundingAllocation::whereId($request->fundingAllocation)->sole()->type;
        if ($funding_allocation_type === 'sga_budget' && $request->sofo_line_number === null) {
            $validator->errors()->add(
                'sofo_line_number',
                'The SOFO Line Number is required for SGA Budget funding allocations.'
            );
        } elseif ($funding_allocation_type !== 'sga_budget' && $request->sofo_line_number !== null) {
            $validator->errors()->add(
                'sofo_line_number',
                'The SOFO Line Number is only permitted for SGA Budget funding allocations.'
            );
        }
    }
}
