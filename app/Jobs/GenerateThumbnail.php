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
    public function __construct(private readonly string $original_file_path)
    {
        $this->queue = 'thumbnail';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! file_exists($this->original_file_path)) {
            return;
        }

        $thumbnail_path = Storage::disk('public')->path('/thumbnail/'.hash_file('sha512', $this->original_file_path));

        // pdftocairo normally appends an extension; we have to do that manually here
        if (file_exists($thumbnail_path.'.png') || file_exists($thumbnail_path.'.jpg')) {
            return;
        }

        $command = 'file --mime-type -b \''.$this->original_file_path.'\'';
        $output = [];
        $exitCode = -1;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new Exception('file returned exit code '.$exitCode.' - '.implode('', $output));
        }

        if ($output[0] === 'image/png') {
            // pdftocairo normally appends an extension; we have to do that manually here
            copy($this->original_file_path, $thumbnail_path.'.png');
        }

        if ($output[0] === 'image/jpeg') {
            // pdftocairo normally appends an extension; we have to do that manually here
            copy($this->original_file_path, $thumbnail_path.'.jpg');
        }

        if ($output[0] !== 'application/pdf') {
            return;
        }

        // Renders PDF to 266px wide, crops out 5 pixels from left, top, and right, resulting in 256px wide image
        $command = 'pdftocairo -png -singlefile -scale-to-x 266 -scale-to-y -1 -x 5 -y 5 -W 256 \''.
            $this->original_file_path.'\' \''.$thumbnail_path.'\'';
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
        if (! file_exists($this->original_file_path)) {
            return '';
        }

        return hash_file('sha512', $this->original_file_path);
    }
}
