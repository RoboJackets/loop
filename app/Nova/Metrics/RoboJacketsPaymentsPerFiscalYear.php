<?php

declare(strict_types=1);

// phpcs:disable PSR2.Methods.FunctionCallSignature.SpaceBeforeCloseBracket

namespace App\Nova\Metrics;

use App\Models\FiscalYear;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

class RoboJacketsPaymentsPerFiscalYear extends Trend
{
    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Reimbursements paid out to RoboJackets for each fiscal year';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): TrendResult
    {
        $payments = DB::query()
            ->select('fiscal_year_ending_year')
            ->selectRaw('sum(expense_payments.amount) as amount')
            ->from(DB::query()
                ->selectRaw('distinct expense_payments.id as expense_payment_id')
                ->selectRaw('min(fiscal_years.ending_year) as fiscal_year_ending_year')
                ->from('expense_payments')
                ->leftJoin('expense_reports', static function (JoinClause $join): void {
                    $join->on(
                        'expense_reports.expense_payment_id',
                        '=',
                        'expense_payments.workday_instance_id'
                    );
                })
                ->leftJoin('fiscal_years', static function (JoinClause $join): void {
                    $join->on('fiscal_years.id', '=', 'expense_reports.fiscal_year_id');
                })
                ->where('expense_payments.status', '=', 'Complete')
                ->groupBy('expense_payments.id')
            )
            ->leftJoin('expense_payments', static function (JoinClause $join): void {
                $join->on('expense_payment_id', '=', 'expense_payments.id');
            })
            ->leftJoin('external_committee_members', static function (JoinClause $join): void {
                $join->on(
                    'external_committee_members.workday_instance_id',
                    '=',
                    'expense_payments.external_committee_member_id'
                );
            })
            ->whereNull('external_committee_members.user_id')
            ->groupBy('fiscal_year_ending_year')
            ->orderBy('fiscal_year_ending_year')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [$row->fiscal_year_ending_year => $row->amount]);

        return (new TrendResult())
            ->trend(
                FiscalYear::whereHas('envelopes')
                    ->orWhereHas('engagePurchaseRequests')
                    ->orWhereHas('emailRequests')
                    ->orderBy('ending_year', 'asc')
                    ->get()
                    ->mapWithKeys(
                        static fn (FiscalYear $fiscalYear): array => [
                            $fiscalYear->ending_year => $payments->get($fiscalYear->ending_year, 0),
                        ]
                    )
                    ->toArray()
            )
            ->showLatestValue()
            ->dollars();
    }

    /**
     * Get the URI key for the metric.
     */
    #[\Override]
    public function uriKey(): string
    {
        return 'robojackets-payments-per-fiscal-year';
    }

    /**
     * Get the displayable name of the metric.
     */
    #[\Override]
    public function name(): string
    {
        return 'RoboJackets Payments Per Fiscal Year';
    }
}
