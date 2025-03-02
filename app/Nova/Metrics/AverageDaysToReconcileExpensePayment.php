<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class AverageDaysToReconcileExpensePayment extends Value
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
    public $helpText = 'Average number of calendar days between expense payment date and check posting date';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->range;

        return $this->result(
            ceil(
                floatval(
                    BankTransaction::selectRaw('avg(datediff(transaction_posted_at, payment_date)) as diff')
                        ->leftJoin('expense_payments', static function (JoinClause $join): void {
                            $join->on('bank_transactions.id', '=', 'expense_payments.bank_transaction_id');
                        })
                        ->when(
                            $range !== 'ALL',
                            static function (EloquentBuilder $query, bool $range_is_not_all) use ($range): void {
                                $query->whereHas(
                                    'expensePayment',
                                    static function (EloquentBuilder $query) use ($range): void {
                                        $query->whereHas(
                                            'expenseReports',
                                            static function (EloquentBuilder $query) use ($range): void {
                                                $query->where(
                                                    'fiscal_year_id',
                                                    static function (QueryBuilder $query) use ($range) {
                                                        $query->select('id')
                                                            ->from('fiscal_years')
                                                            ->where('ending_year', $range);
                                                    }
                                                );
                                            }
                                        );
                                    }
                                );
                            }
                        )
                        ->whereNotNull('transaction_posted_at')
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
        return 'Days to Reconcile';
    }
}
