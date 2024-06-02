<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Console\Commands;

use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Generates thumbnails for all attachments.
 *
 * @phan-suppress PhanUnreferencedClass
 */
class GenerateThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:thumbnails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate thumbnails for all attachments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Attachment::all()->each(static function (Attachment $attachment, int $key): void {
            GenerateThumbnail::dispatch(Storage::disk('local')->path($attachment->filename));
        });
        DocuSignEnvelope::all()->each(static function (DocuSignEnvelope $envelope, int $key): void {
            GenerateThumbnail::dispatch(Storage::disk('local')->path($envelope->sofo_form_filename));
            if ($envelope->summary_filename !== null) {
                GenerateThumbnail::dispatch(Storage::disk('local')->path($envelope->summary_filename));
            }
        });
        EmailRequest::all()->each(static function (EmailRequest $emailRequest, int $key): void {
            GenerateThumbnail::dispatch(Storage::disk('local')->path($emailRequest->vendor_document_filename));
        });

        return Command::SUCCESS;
    }
}
