<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EmailRequest $email): StreamedResponse
    {
        return Storage::download($email->vendor_document_filename);
    }
}
