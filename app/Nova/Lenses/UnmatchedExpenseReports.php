<?php

declare(strict_types=1);

namespace App\Nova\Lenses;

use App\Nova\ExternalCommitteeMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class UnmatchedExpenseReports extends Lens
{
    /**
     * The displayable name of the lens.
     *
     * @var string
     */
    public $name = 'Expense Reports with No Envelopes';

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ExpenseReport>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ExpenseReport>
     */
    public static function query(LensRequest $request, $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query->whereDoesntHave('envelopes')
                ->where('status', '!=', 'Canceled')
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @return array<int,\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Number', 'workday_expense_report_id')
                ->sortable(),

            Date::make('Created', 'created_date')
                ->sortable(),

            Text::make('Status')
                ->sortable(),

            BelongsTo::make('Pay To', 'payTo', ExternalCommitteeMember::class)
                ->sortable(),

            Text::make('Memo')
                ->sortable()
                ->displayUsing(static fn (string $memo): string => Str::limit($memo, 50)),

            Currency::make('Amount')
                ->sortable(),
        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @return array<\Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @return array<\Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the URI key for the lens.
     */
    public function uriKey(): string
    {
        return 'unmatched-expense-reports';
    }
}
