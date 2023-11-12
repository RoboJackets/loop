<?php

declare(strict_types=1);

namespace App\Nova\Metrics;

use App\Models\FiscalYear;

trait FiscalYearRanges
{
    /**
     * Get the ranges available for the metric.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        $ranges = FiscalYear::whereHas('envelopes')
            ->orWhereHas('engagePurchaseRequests')
            ->get()
            ->sortByDesc('ending_year')
            ->mapWithKeys(
                static fn (FiscalYear $fiscal_year, int $key): array => [
                    $fiscal_year->ending_year => 'FY'.$fiscal_year->ending_year,
                ]
            )
            ->toArray();

        $ranges['ALL'] = 'All Time';

        return $ranges;
    }
}
