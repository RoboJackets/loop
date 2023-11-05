<?php

declare(strict_types=1);

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $pdf_path)
    {
        $this->queue = 'thumbnail';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $thumbnail_path = Storage::disk('public')->path('/thumbnail/'.hash_file('sha512', $this->pdf_path));

        if (! file_exists($this->pdf_path)) {
            return;
        }

        $command = 'file --mime-type -b \''.escapeshellarg($this->pdf_path).'\'';
        $output = [];
        $exitCode = -1;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new Exception('file returned exit code '.$exitCode.' - '.implode('', $output));
        }

        if ($output[0] !== 'application/pdf') {
            return;
        }

        if (file_exists($thumbnail_path)) {
            return;
        }

        // Renders PDF to 266px wide, crops out 5 pixels from left, top, and right, resulting in 256px wide image
        $command = 'pdftocairo -png -singlefile -scale-to-x 266 -scale-to-y -1 -x 5 -y 5 -W 256 \''.
            escapeshellarg($this->pdf_path).'\' \''.$thumbnail_path.'\'';
        $output = [];
        $exitCode = -1;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new Exception('pdftocairo returned exit code '.$exitCode.' - '.implode('', $output));
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        if (! file_exists($this->pdf_path)) {
            return '';
        }

        return hash_file('sha512', $this->pdf_path);
    }
}
