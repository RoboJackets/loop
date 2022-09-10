<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocuSignEnvelope;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DocuSignEnvelope $envelope): BinaryFileResponse
    {
        return response()->download($envelope->sofo_form_filename);
    }
}
