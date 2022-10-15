<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\MatchExpenseReport;
use App\Models\ExpenseReportLine;

class ExpenseReportLineObserver
{
    public function saved(ExpenseReportLine $expenseReportLine): void
    {
        MatchExpenseReport::dispatch($expenseReportLine->expenseReport);
    }
}
