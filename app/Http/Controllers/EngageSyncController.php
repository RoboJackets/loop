<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\EngagePurchaseRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class EngageSyncController extends Controller
{
    public function getRequestsToSync(): JsonResponse
    {
        $missing_submitted_by_user = EngagePurchaseRequest::whereDoesntHave('submittedBy')
            ->get()
            ->pluck('engage_id');

        $missing_approved_by_user = EngagePurchaseRequest::whereDoesntHave('approvedBy')
            ->get()
            ->pluck('engage_id');

        $missing_attachments = EngagePurchaseRequest::whereDoesntHave('attachments')
            ->get()
            ->pluck('engage_id');

        $not_approved = EngagePurchaseRequest::where('status', '!=', 'Approved')
            ->get()
            ->pluck('engage_id');

        $request_engage_ids = array_values(collect($missing_submitted_by_user)
            ->concat($missing_approved_by_user)
            ->concat($missing_attachments)
            ->concat($not_approved)
            ->uniqueStrict()
            ->toArray());

        sort($request_engage_ids);

        return response()->json([
            'requests' => $request_engage_ids,
        ]);
    }

    public function syncComplete(): JsonResponse
    {
        DataSource::updateOrCreate(
            [
                'name' => 'engage',
            ],
            [
                'synced_at' => Carbon::now(),
            ]
        );

        return response()->json(
            [
                'status' => 'ok',
            ]
        );
    }
}
