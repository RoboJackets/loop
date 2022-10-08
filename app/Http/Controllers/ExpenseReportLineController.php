<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExpenseReportLine;
use App\Models\Attachment;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use App\Util\Workday;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExpenseReportLineController extends Controller
{
    /**
     * Update an existing expense report line.
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanTypeMismatchDimFetch
     * @phan-suppress PhanPossiblyNullTypeArgumentInternal
     */
    public function update(
        ExpenseReport $expense_report,
        ExpenseReportLine $line,
        UpdateExpenseReportLine $request
    ): JsonResponse {
        $attachment_rows = Workday::searchForKeyValuePair(
            $request['body']['children'][0]['children'][0]['children'],
            'widget',
            'fileUpload2Row'
        );
        $ids_to_upload = [];

        foreach ($attachment_rows as $row) {
            $instance_id = Workday::getInstanceId($row['attachment']);
            $filename = 'workday/'.$instance_id.'/'.$row['file']['fileName'];

            Attachment::updateOrCreate(
                [
                    'workday_instance_id' => $instance_id,
                ],
                [
                    'filename' => $filename,
                    'workday_uploaded_by_worker_id' => Workday::getInstanceId($row['uploadedBy']),
                    'workday_uploaded_at' => Carbon::parse($row['uploadedDate']['value']['V']),
                    'workday_comment' => array_key_exists(
                        'value',
                        $row['contentArea'][0]
                    ) ? $row['contentArea'][0]['value'] : null,
                    'attachable_type' => $line->getMorphClass(),
                    'attachable_id' => $line->id,
                ]
            );

            if (! Storage::exists($filename)) {
                $ids_to_upload[] = $instance_id;
            }
        }

        return response()->json([
            'attachments' => $ids_to_upload,
        ]);
    }
}
