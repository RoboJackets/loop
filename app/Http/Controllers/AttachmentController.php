<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadWorkdayAttachment;
use App\Jobs\MatchExpenseReport;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;

class AttachmentController extends Controller
{
    public function __invoke(Attachment $attachment, UploadWorkdayAttachment $request): JsonResponse
    {
        $file = $request->file('attachment');

        $file->storeAs('workday/'.$attachment['workday_instance_id'], $file->getClientOriginalName());

        MatchExpenseReport::dispatch($attachment->attachable->expenseReport);

        return response()->json($attachment);
    }
}
