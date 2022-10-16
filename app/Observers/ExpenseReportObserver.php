<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\MatchExpenseReport;
use App\Models\ExpenseReport;

class ExpenseReportObserver
{
    public function saved(ExpenseReport $expenseReport): void
    {
        MatchExpenseReport::dispatch($expenseReport);
    }
}
