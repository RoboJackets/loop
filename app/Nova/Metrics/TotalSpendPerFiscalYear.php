<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Models\FiscalYear;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

class TotalSpendPerFiscalYear extends Trend
{
    /**
     * The help text for the metric.
     *
     * @var string
     */
    public $helpText = 'Approximate total spending from Georgia Tech funds for each fiscal year';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): TrendResult
    {
        $docusign_spend = DocuSignEnvelope::select('ending_year')
            ->selectRaw('coalesce(sum(docusign_envelopes.amount),0) as spend')
            ->leftJoin('fiscal_years', static function (JoinClause $join): void {
                $join->on('fiscal_years.id', '=', 'docusign_envelopes.fiscal_year_id');
            })
            ->where('lost', '=', false)
            ->where('submission_error', '=', false)
            ->whereNull('duplicate_of_docusign_envelope_id')
            ->whereNull('deleted_at')
            ->whereNotNull('fiscal_year_id')
            ->groupBy('ending_year');

        $engage_spend = EngagePurchaseRequest::select('ending_year')
            ->selectRaw('coalesce(sum(engage_purchase_requests.submitted_amount),0) as spend')
            ->leftJoin('fiscal_years', static function (JoinClause $join): void {
                $join->on('fiscal_years.id', '=', 'engage_purchase_requests.fiscal_year_id');
            })
            ->whereIn('status', ['Approved', 'Completed'])
            ->where('current_step_name', '=', 'Check Request Sent')
            ->whereNull('deleted_at')
            ->whereNotNull('fiscal_year_id')
            ->groupBy('ending_year');

        $email_spend = EmailRequest::select('ending_year')
            ->selectRaw('coalesce(sum(email_requests.vendor_document_amount),0) as spend')
            ->leftJoin('fiscal_years', static function (JoinClause $join): void {
                $join->on('fiscal_years.id', '=', 'email_requests.fiscal_year_id');
            })
            ->whereNull('deleted_at')
            ->whereNotNull('fiscal_year_id')
            ->groupBy('ending_year');

        $total_spend = DB::query()
            ->select('ending_year')
            ->selectRaw('sum(spend) as spend')
            ->fromSub(
                query: $docusign_spend
                    ->union($engage_spend)
                    ->union($email_spend),
                as: 'all_spend'
            )
            ->groupBy('ending_year')
            ->orderBy('ending_year')
            ->get()
            ->mapWithKeys(
                static fn (object $fiscal_year): array => [
                    $fiscal_year->ending_year => $fiscal_year->spend,
                ]
            );

        return (new TrendResult())
            ->trend(
                FiscalYear::whereHas('envelopes')
                    ->orWhereHas('engagePurchaseRequests')
                    ->orWhereHas('emailRequests')
                    ->orderBy('ending_year', 'asc')
                    ->get()
                    ->mapWithKeys(
                        static fn (FiscalYear $fiscalYear): array => [
                            $fiscalYear->ending_year => $total_spend->get($fiscalYear->ending_year, 0),
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
        return 'total-spend-per-fiscal-year';
    }
}
