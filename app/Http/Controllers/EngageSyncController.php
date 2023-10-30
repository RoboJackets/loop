<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EngagePurchaseRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EngageSyncController extends Controller
{
    public function getRequestsToSync(): JsonResponse
    {
        $missing_submitted_by_user = EngagePurchaseRequest::whereDoesntHave('submittedBy')
            ->get()
            ->pluck('engage_id');

        $missing_approved_by_user = EngagePurchaseRequest::whereDoesntHave('approvedBy')
            ->where('approved', '=', true)
            ->get()
            ->pluck('engage_id');

        $missing_attachments = EngagePurchaseRequest::whereDoesntHave('attachments')
            ->get()
            ->pluck('engage_id');

        $not_approved = EngagePurchaseRequest::where('approved', '=', false)
            ->get()
            ->pluck('engage_id');

        $request_engage_ids = collect($missing_submitted_by_user)
            ->concat($missing_approved_by_user)
            ->concat($missing_attachments)
            ->concat($not_approved)
            ->uniqueStrict()
            ->toArray();

        return response()->json([
            'requests' => array_values($request_engage_ids),
        ]);
    }

    public function syncComplete(): JsonResponse
    {
        Cache::put('last_engage_sync', Carbon::now()->unix());

        return response()->json(
            [
                'status' => 'ok',
            ]
        );
    }
}
