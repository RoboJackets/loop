<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExpensePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class CancelExpensePayments implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ExpensePayment::whereDoesntHave('expenseReports')
            ->update(['status' => 'Canceled']);
    }
}
