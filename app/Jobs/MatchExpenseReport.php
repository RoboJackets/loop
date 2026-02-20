<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter
// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Jobs;

use App\Exceptions\CouldNotExtractEngagePurchaseRequestNumber;
use App\Models\Attachment;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MatchExpenseReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @psalm-mutation-free
     */
    public function __construct(private readonly ExpenseReport $expenseReport)
    {
        $this->queue = 'expense-report-match';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->expenseReport->status === 'Canceled') {
            EngagePurchaseRequest::whereExpenseReportId($this->expenseReport->id)
                ->update(['expense_report_id' => null]);
        } else {
            try {
                $purchase_request = EngagePurchaseRequest::whereApprovedAmount($this->expenseReport->amount)
                    ->whereDoesntHave('expenseReport')
                    ->where('status', '=', 'Approved')
                    ->whereDate('approved_at', '<=', $this->expenseReport->created_date)
                    ->where('approved_by_user_id', '=', $this->expenseReport->createdBy->id)
                    ->sole();

                $purchase_request->expense_report_id = $this->expenseReport->id;
                $purchase_request->save();
            } catch (ModelNotFoundException|MultipleRecordsFoundException) {
                $engage_request_numbers = [];

                $this->expenseReport->lines->each(
                    static function (ExpenseReportLine $line, int $key) use (&$engage_request_numbers): void {
                        $line->attachments->each(
                            static function (Attachment $attachment, int $key) use (&$engage_request_numbers): void {
                                if (
                                    str_ends_with(strtolower($attachment->filename), '.pdf') &&
                                    Storage::disk('local')->exists($attachment->filename)
                                ) {
                                    try {
                                        $engage_request_numbers[] =
                                            EngagePurchaseRequest::getPurchaseRequestNumberFromText(
                                                $attachment->toSearchableArray()['full_text']
                                            );
                                    } catch (CouldNotExtractEngagePurchaseRequestNumber|FileNotFoundException) {
                                        return;
                                    }
                                }
                            }
                        );
                    }
                );

                $collection = collect($engage_request_numbers)->uniqueStrict();

                if ($collection->count() === 1) {
                    try {
                        $purchase_request = EngagePurchaseRequest::whereEngageRequestNumber($collection->sole())
                            ->whereDoesntHave('expenseReport')
                            ->where('status', '=', 'Approved')
                            ->sole();

                        $purchase_request->expense_report_id = $this->expenseReport->id;
                        $purchase_request->save();
                    } catch (ModelNotFoundException) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * The unique ID of the job.
     *
     * @psalm-mutation-free
     */
    public function uniqueId(): string
    {
        return strval($this->expenseReport->id);
    }
}
