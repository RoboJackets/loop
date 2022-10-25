<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter
// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Jobs;

use App\Exceptions\CouldNotExtractEnvelopeUuid;
use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
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
                ->where('lost', '=', false)
                ->update(['expense_report_id' => null]);
        } else {
            try {
                $envelope = DocuSignEnvelope::whereAmount($this->expenseReport->amount)
                    ->whereDoesntHave('expenseReport')
                    ->whereDoesntHave('replacedBy')
                    ->whereDoesntHave('duplicateOf')
                    ->whereIn('type', ['purchase_reimbursement', 'travel_reimbursement'])
                    ->whereDate('submitted_at', '<=', $this->expenseReport->created_date)
                    ->where('internal_cost_transfer', '=', false)
                    ->sole();

                $envelope->expense_report_id = $this->expenseReport->id;
                $envelope->save();
            } catch (ModelNotFoundException|MultipleRecordsFoundException) {
                $envelope_uuids = [];

                $this->expenseReport->lines->each(
                    static function (ExpenseReportLine $line, int $key) use (&$envelope_uuids): void {
                        $line->attachments->each(
                            static function (Attachment $attachment, int $key) use (&$envelope_uuids): void {
                                try {
                                    $envelope_uuids[] = DocuSignEnvelope::getEnvelopeUuidFromSummaryPdf(
                                        Storage::disk('local')->path($attachment->filename)
                                    );
                                } catch (CouldNotExtractEnvelopeUuid|FileNotFoundException) {
                                    return;
                                }
                            }
                        );
                    }
                );

                $collection = collect($envelope_uuids)->uniqueStrict();

                if ($collection->count() === 1) {
                    try {
                        $envelope = DocuSignEnvelope::whereEnvelopeUuid($collection->sole())
                            ->whereDoesntHave('expenseReport')
                            ->whereDoesntHave('replacedBy')
                            ->whereDoesntHave('duplicateOf')
                            ->whereIn('type', ['purchase_reimbursement', 'travel_reimbursement'])
                            ->where('internal_cost_transfer', '=', false)
                            ->whereDate('submitted_at', '<=', $this->expenseReport->created_date)
                            ->sole();

                        $envelope->expense_report_id = $this->expenseReport->id;
                        $envelope->save();
                    } catch (ModelNotFoundException) {
                        return;
                    }
                }
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
