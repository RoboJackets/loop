<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\MatchExpenseReport;
use App\Models\DocuSignEnvelope;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReport;

class ExpenseReportObserver
{
    public function saved(ExpenseReport $expenseReport): void
    {
        if (
            DocuSignEnvelope::whereExpenseReportId($expenseReport->id)->doesntExist() &&
            EngagePurchaseRequest::whereExpenseReportId($expenseReport->id)->doesntExist()
        ) {
            MatchExpenseReport::dispatch($expenseReport);
        }
    }
}
