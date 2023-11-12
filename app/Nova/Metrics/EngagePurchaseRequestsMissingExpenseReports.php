<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Nova\Metrics;

use App\Models\EngagePurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class EngagePurchaseRequestsMissingExpenseReports extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'inbox-in';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Engage requests that have been submitted in Engage, but have not been entered into Workday yet';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this
            ->result(
                EngagePurchaseRequest::selectRaw('coalesce(sum(submitted_amount), 0) as total')
                    ->whereDoesntHave('expenseReport')
                    ->where(static function (Builder $query): void {
                        $query->where('payee_first_name', 'like', '%robojackets%')
                            ->orWhere('payee_last_name', 'like', '%robojackets%');
                    })
                    ->whereNotNull('submitted_at')
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

    /**
     * Get the displayable name of the metric.
     */
    public function name(): string
    {
        return 'Engage Requests Missing Expense Reports';
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'engage-purchase-requests-missing-expense-reports';
    }
}
