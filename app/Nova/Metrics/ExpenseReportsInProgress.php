<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpenseReport;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

class ExpenseReportsInProgress extends Table
{
    /**
     * The text to be displayed when the table is empty.
     *
     * @var string
     */
    public $emptyText = 'No expense reports in progress';

    /**
     * Get the displayable name of the metric.
     */
    public function name(): string
    {
        return 'Expense Reports In Progress';
    }

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): array
    {
        return ExpenseReport::whereNotIn('status', ['Paid', 'Canceled'])
            ->whereHas('payTo', static function (Builder $query): void {
                $query->whereNull('user_id');
            })
            ->orderBy('created_date')
            ->get()
            ->map(
                static fn (ExpenseReport $expenseReport) => MetricTableRow::make()
                    ->icon($expenseReport->status === 'Approved' ? 'check' : 'search')
                    ->iconClass($expenseReport->status === 'Approved' ? 'text-green-500' : 'text-sky-500')
                    ->title($expenseReport->workday_expense_report_id)
                    ->subtitle($expenseReport->created_date->format('Y-m-d')
                        .' | '.$expenseReport->status
                        .' | $'.number_format(abs($expenseReport->amount), 2))
                    ->actions(static fn (): array => [
                        MenuItem::link(
                            'View in Loop',
                            'resources/'.\App\Nova\ExpenseReport::uriKey().'/'.$expenseReport->id
                        ),
                        MenuItem::externalLink('View in Workday', $expenseReport->workday_url),
                    ])
            )
            ->toArray();
    }
}
