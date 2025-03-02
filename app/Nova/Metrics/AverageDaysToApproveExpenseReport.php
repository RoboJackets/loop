<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\ExpenseReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class AverageDaysToApproveExpenseReport extends Value
{
    use FiscalYearRanges;

    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'calendar';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Average number of calendar days between creation and approval of expense reports';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->range;

        return $this->result(
            ceil(
                floatval(
                    ExpenseReport::selectRaw('avg(datediff(approval_date, created_date)) as diff')
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
                        ->whereNotNull('approval_date')
                        ->sole()->diff
                )
            )
        )
            ->suffix(' days');
    }

    /**
     * Get the displayable name of the metric.
     */
    #[\Override]
    public function name(): string
    {
        return 'Days to Approve';
    }
}
