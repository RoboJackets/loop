<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadEngageAttachment;
use App\Models\Attachment;
use App\Models\EngagePurchaseRequest;
use Illuminate\Http\JsonResponse;

class EngageAttachmentController extends Controller
{
    /**
     * Store a newly uploaded attachment.
     */
    public function store(EngagePurchaseRequest $purchase_request, UploadEngageAttachment $request): JsonResponse
    {
        $file = $request->file('attachment');

        $attachment = Attachment::create([
            'attachable_type' => $purchase_request->getMorphClass(),
            'attachable_id' => $purchase_request->id,
            'filename' => 'engage/'.$request['documentId'].'/'.$file->getClientOriginalName(),
            'engage_document_id' => $request['documentId'],
        ]);

        $file->storeAs('engage/'.$request['documentId'], $file->getClientOriginalName());

        $attachment->searchable();

        return response()->json($attachment);
    }

    /**
     * Display the attachment.
     */
    public function show(EngagePurchaseRequest $purchase_request, Attachment $attachment): JsonResponse
    {
        return response()->json($attachment);
    }
}
