<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class AverageDaysToPayExpenseReport extends Value
{
    use FiscalYearRanges;

    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'document-check';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Average number of calendar days between expense report approval and check date';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->range;

        return $this->result(
            ceil(
                ExpensePayment::selectRaw('avg(datediff(payment_date, approval_date)) as diff')
                    ->leftJoin('expense_reports', static function (JoinClause $join): void {
                        $join->on('expense_payments.workday_instance_id', '=', 'expense_payment_id');
                    })
                    ->when(
                        $range !== 'ALL',
                        static function (EloquentBuilder $query, bool $range_is_not_all) use ($range): void {
                            $query->where(
                                'expense_reports.fiscal_year_id',
                                static function (QueryBuilder $query) use ($range) {
                                    $query->select('id')
                                        ->from('fiscal_years')
                                        ->where('ending_year', $range);
                                }
                            );
                        }
                    )
                    ->sole()->diff
            )
        )
            ->suffix(' days');
    }

    /**
     * Get the displayable name of the metric.
     */
    public function name(): string
    {
        return 'Days to Pay';
    }
}
