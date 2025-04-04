<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A Nova resource for Workday Expense Report Lines.
 *
 * @extends \App\Nova\Resource<\App\Models\ExpenseReportLine>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class ExpenseReportLine extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\ExpenseReportLine::class;

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
        'id',
    ];

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Workday';

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<string>
     */
    public static $with = [
        'expenseReport',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    #[\Override]
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Number::make('Workday Line ID')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday')),

            BelongsTo::make('Expense Report', 'expenseReport', ExpenseReport::class),

            Currency::make('Amount')
                ->sortable(),

            Text::make('Memo')
                ->sortable(),

            MorphMany::make('Attachments', 'attachments', Attachment::class),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    #[\Override]
    public function subtitle(): string
    {
        return $this->expenseReport->created_date->format('Y-m-d')
            .' | '.$this->memo
            .' | $'.number_format(abs($this->amount), 2);
    }
}
