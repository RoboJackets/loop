<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadWorkdayAttachment;
use App\Jobs\MatchExpenseReport;
use App\Models\Attachment;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use Illuminate\Http\JsonResponse;

class WorkdayAttachmentController extends Controller
{
    public function __invoke(
        ExpenseReport $expense_report,
        ExpenseReportLine $line,
        Attachment $attachment,
        UploadWorkdayAttachment $request
    ): JsonResponse {
        $file = $request->file('attachment');

        $file->storeAs('workday/'.$attachment['workday_instance_id'], $file->getClientOriginalName());

        MatchExpenseReport::dispatch($attachment->attachable->expenseReport);
        $attachment->searchable();

        return response()->json($attachment);
    }
}
