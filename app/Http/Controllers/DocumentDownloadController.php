<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocuSignEnvelope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DocuSignEnvelope $uuid): StreamedResponse
    {
        return Storage::download($uuid->sofo_form_filename);
    }
}
