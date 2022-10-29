<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class ExpensePaymentsPendingReconciliation extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'cash';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Expense payments (checks) that have been sent but not yet reconciled';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this
            ->result(
                ExpensePayment::selectRaw('coalesce(sum(amount), 0) as total')
                    ->where('reconciled', '=', false)
                    ->whereHas('payTo', static function (Builder $query): void {
                        $query->whereNull('user_id');
                    })
                    ->sole()->total
            )
            ->dollars()
            ->allowZeroResult();
    }

    /**
     * Get the ranges available for the metric.
     */
    public function ranges(): array
    {
        return [];
    }
}
