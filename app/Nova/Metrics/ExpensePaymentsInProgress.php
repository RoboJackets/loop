<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

class ExpensePaymentsInProgress extends Table
{
    /**
     * The text to be displayed when the table is empty.
     *
     * @var string
     */
    public $emptyText = 'No expense payments in progress';

    /**
     * Get the displayable name of the metric.
     */
    #[\Override]
    public function name(): string
    {
        return 'Expense Payments In Progress';
    }

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): array
    {
        return ExpensePayment::where('reconciled', '=', false)
            ->whereDoesntHave('bankTransaction')
            ->whereHas('payTo', static function (Builder $query): void {
                $query->whereNull('user_id');
            })
            ->whereHas('expenseReports')
            ->orderBy('payment_date')
            ->get()
            ->map(
                static fn (ExpensePayment $expensePayment) => MetricTableRow::make()
                    ->icon('cash')
                    ->iconClass('text-sky-500')
                    ->title($expensePayment->transaction_reference)
                    ->subtitle($expensePayment->payment_date->format('Y-m-d')
                        .' | $'.number_format(abs($expensePayment->amount), 2))
                    ->actions(static fn (): array => [
                        MenuItem::link(
                            'View in Loop',
                            'resources/'.\App\Nova\ExpensePayment::uriKey().'/'.$expensePayment->id
                        ),
                    ])
            )
            ->toArray();
    }
}
