<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocuSignEnvelope;
use App\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchExpenseReport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly ExpenseReport $expenseReport)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->expenseReport->status === 'Canceled') {
            DocuSignEnvelope::whereExpenseReportId($this->expenseReport->id)
                ->update(['expense_report_id' => null]);
        } else {
            try {
                $envelope = DocuSignEnvelope::whereAmount($this->expenseReport->amount)
                    ->whereDoesntHave('expenseReport')
                    ->whereDoesntHave('replacedBy')
                    ->whereIn('type', ['purchase_reimbursement', 'travel_reimbursement'])
                    ->whereDate('submitted_at', '<=', $this->expenseReport->created_date)
                    ->sole();

                $envelope->expense_report_id = $this->expenseReport->id;
                $envelope->save();
            } catch (ModelNotFoundException | MultipleRecordsFoundException) {
                return;
            }
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return strval($this->expenseReport->id);
    }
}
