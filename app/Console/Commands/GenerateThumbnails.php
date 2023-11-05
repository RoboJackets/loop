<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Console\Commands;

use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
            GenerateThumbnail::dispatch(Storage::disk('local')->path($envelope->summary_filename));
        });

        return Command::SUCCESS;
    }
}
