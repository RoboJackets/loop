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
    public function __invoke(DocuSignEnvelope $envelope): StreamedResponse
    {
        return Storage::download($envelope->sofo_form_filename);
    }
}
