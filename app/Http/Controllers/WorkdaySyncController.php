<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ExpensePayment;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class WorkdaySyncController extends Controller
{
    public function getResourcesToSync(): JsonResponse
    {
        $in_progress = ExpenseReport::whereNotIn('status', ['Canceled', 'Paid'])
            ->get()
            ->pluck('workday_instance_id');

        $without_payment = ExpenseReport::whereStatus('Paid')
            ->whereDoesntHave('expensePayment')
            ->get()
            ->pluck('workday_instance_id');

        $unreconciled_payment = ExpenseReport::whereHas(
            'expensePayment',
            static function (EloquentBuilder $query): void {
                $query->where('reconciled', '=', false)
                    ->whereHas('payTo', static function (EloquentBuilder $query): void {
                        $query->whereDoesntHave('user');
                    });
            }
        )
            ->get()
            ->pluck('workday_instance_id');

        $attachments = [];

        Attachment::whereAttachableType(ExpenseReportLine::getMorphClassStatic())
            ->get()
            ->each(static function (Attachment $attachment, int $key) use (&$attachments): void {
                if (! Storage::exists($attachment->filename)) {
                    $attachments[] = $attachment->attachable_id;
                }
            });

        $missing_attachments = ExpenseReport::whereHas(
            'lines',
            static function (EloquentBuilder $query) use ($attachments): void {
                $query->whereIn('id', $attachments);
            }
        )
            ->get()
            ->pluck('workday_instance_id');

        $sync_expense_reports = collect($in_progress)
            ->concat($without_payment)
            ->concat($unreconciled_payment)
            ->concat($missing_attachments)
            ->uniqueStrict()
            ->toArray();

        $attachments = Attachment::whereNotIn(
            'workday_uploaded_by_worker_id',
            static function (QueryBuilder $query): void {
                $query->select('workday_instance_id')
                    ->from('users');
            }
        )
            ->get()
            ->pluck('workday_uploaded_by_worker_id');

        $expense_reports = ExpenseReport::whereNotIn(
            'created_by_worker_id',
            static function (QueryBuilder $query): void {
                $query->select('workday_instance_id')
                    ->from('users');
            }
        )
            ->get()
            ->pluck('created_by_worker_id');

        $sync_workers = collect($attachments)
            ->concat($expense_reports)
            ->uniqueStrict()
            ->toArray();

        $expense_reports = ExpenseReport::whereNotIn(
            'external_committee_member_id',
            static function (QueryBuilder $query): void {
                $query->select('workday_instance_id')
                    ->from('external_committee_members');
            }
        )
            ->get()
            ->pluck('external_committee_member_id');

        $expense_payments = ExpensePayment::whereNotIn(
            'external_committee_member_id',
            static function (QueryBuilder $query): void {
                $query->select('workday_instance_id')
                    ->from('external_committee_members');
            }
        )
            ->get()
            ->pluck('external_committee_member_id');

        $sync_external_committee_members = array_values(collect($expense_reports)
            ->concat($expense_payments)
            ->uniqueStrict()
            ->toArray());

        return response()->json(
            [
                'expense-reports' => array_values($sync_expense_reports),
                'workers' => $sync_workers,
                'external-committee-members' => $sync_external_committee_members,
            ]
        );
    }

    public function syncComplete(): JsonResponse
    {
        Cache::put('last_workday_sync', Carbon::now()->unix());

        return response()->json(
            [
                'status' => 'ok',
            ]
        );
    }
}
