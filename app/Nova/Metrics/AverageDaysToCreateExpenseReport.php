<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Nova\Metrics;

use App\Models\DocuSignEnvelope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class AverageDaysToCreateExpenseReport extends Value
{
    use FiscalYearRanges;

    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'inbox-arrow-down';

    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Average number of calendar days between submission of a DocuSign form and creation of the expense report';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->range;

        return $this->result(
            ceil(
                floatval(
                    DocuSignEnvelope::selectRaw(
                        'avg(datediff(expense_reports.created_date, docusign_envelopes.submitted_at)) as diff'
                    )
                    ->leftJoin('expense_reports', static function (JoinClause $join): void {
                        $join->on('expense_reports.id', '=', 'expense_report_id');
                    })
                    ->when(
                        $range !== 'ALL',
                        static function (EloquentBuilder $query, bool $range_is_not_all) use ($range): void {
                            $query->where(
                                'docusign_envelopes.fiscal_year_id',
                                static function (QueryBuilder $query) use ($range) {
                                    $query->select('id')
                                        ->from('fiscal_years')
                                        ->where('ending_year', $range);
                                }
                            );
                        }
                    )
                    ->whereNotNull('created_date')
                    ->sole()->diff
                )
            )
        )
            ->suffix(' days');
    }

    /**
     * Get the displayable name of the metric.
     */
    public function name(): string
    {
        return 'Days to Create';
    }
}
