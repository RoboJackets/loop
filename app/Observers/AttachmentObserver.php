<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\MatchAttachment;
use App\Jobs\MatchExpenseReport;
use App\Models\Attachment;
use App\Models\ExpenseReportLine;

class AttachmentObserver
{
    public function saved(Attachment $attachment): void
    {
        if ($attachment->attachable_type === ExpenseReportLine::getMorphClassStatic()) {
            MatchExpenseReport::dispatch($attachment->attachable->expenseReport);
            MatchAttachment::dispatch($attachment);
        }
    }
}
