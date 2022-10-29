<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpenseReport;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class ExpenseReportsPendingApproval extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'document-magnifying-glass';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Expense reports that have been created in Workday but are not yet approved';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this
            ->result(
                ExpenseReport::selectRaw('coalesce(sum(amount), 0) as total')
                    ->whereNotIn('status', ['Paid', 'Canceled'])
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
