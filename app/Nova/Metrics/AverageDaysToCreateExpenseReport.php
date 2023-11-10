<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Nova\Metrics;

use App\Models\DocuSignEnvelope;
use App\Models\ExpenseReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use function Clue\StreamFilter\fun;

class AverageDaysToCreateExpenseReport extends Value
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
    public $helpText = 'Average number of calendar days between submission of a reimbursement request and creation of the expense report';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->range;

        return $this->result(
            ceil(
                floatval(
                    ExpenseReport::selectRaw(
                        'avg(datediff(expense_reports.created_date, coalesce(docusign_envelopes.submitted_at, '.
                        'engage_purchase_requests.submitted_at))) as diff',
                    )
                        ->leftJoin('docusign_envelopes', static function (JoinClause $join): void {
                            $join->on('docusign_envelopes.expense_report_id', '=', 'expense_reports.id');
                        })
                        ->leftJoin('engage_purchase_requests', static function (JoinClause $join): void {
                            $join->on('engage_purchase_requests.expense_report_id', '=', 'expense_reports.id');
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
                        ->where(static function (EloquentBuilder $query) {
                            $query->whereNotNull('docusign_envelopes.id')
                                ->orWhereNotNull('engage_purchase_requests.id');
                        })
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
