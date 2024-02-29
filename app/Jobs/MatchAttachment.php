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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MatchAttachment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Attachment $attachment)
    {
        $this->queue = 'expense-report-match';
    }

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeMismatchArgumentNullable
     */
    public function handle(): void
    {
        if (
            str_ends_with(strtolower($this->attachment->filename), '.pdf') &&
            Storage::disk('local')->exists($this->attachment->filename)
        ) {
            try {
                $purchase_request_number = EngagePurchaseRequest::getPurchaseRequestNumberFromText(
                    $this->attachment->toSearchableArray()['full_text']
                );
            } catch (CouldNotExtractEngagePurchaseRequestNumber|FileNotFoundException) {
                return;
            }
        } else {
            return;
        }

        try {
            $purchase_request = EngagePurchaseRequest::whereEngageRequestNumber($purchase_request_number)
                ->whereDoesntHave('expenseReport')
                ->where('status', '=', 'Approved')
                ->sole();

            $purchase_request->expense_report_id = $this->attachment->attachable->expenseReport->id;
            $purchase_request->save();
        } catch (ModelNotFoundException) {
            return;
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return strval($this->attachment->id);
    }
}
