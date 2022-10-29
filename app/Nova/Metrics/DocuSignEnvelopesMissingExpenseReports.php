<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Nova\Metrics;

use App\Models\DocuSignEnvelope;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class DocuSignEnvelopesMissingExpenseReports extends Value
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
    public $helpText = 'Purchase and travel reimbursement forms that have been submitted to SOFO, but have not been entered into Workday yet';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this
            ->result(
                DocuSignEnvelope::selectRaw('coalesce(sum(amount), 0) as total')
                    ->whereDoesntHave('expenseReport')
                    ->whereDoesntHave('replacedBy')
                    ->whereDoesntHave('duplicateOf')
                    ->whereDoesntHave('payToUser')
                    ->whereIn('type', ['purchase_reimbursement', 'travel_reimbursement'])
                    ->where('internal_cost_transfer', '=', false)
                    ->where('lost', '=', false)
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
        return 'Reimbursements Missing Expense Reports';
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'docusign-envelopes-missing-expense-reports';
    }
}
