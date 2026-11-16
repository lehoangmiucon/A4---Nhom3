<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PrivatizeRepairAttachments extends Command
{
    protected $signature = 'attachments:privatize-repairs {--dry-run : List files without changing storage}';

    protected $description = 'Move legacy repair evidence out of public storage after verifying each private copy';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('repair-evidence');
        $failed = 0;
        $moved = 0;

        // Include orphaned images too: a deleted DB record does not make a public file safe.
        foreach ($public->allFiles('ticket-attachments/repairs') as $path) {
            if ($this->option('dry-run')) {
                $this->line($path);

                continue;
            }

            try {
                $contents = $public->get($path);
                if ($contents === null) {
                    throw new \RuntimeException('Cannot read public file.');
                }
                if (! $private->exists($path)) {
                    if (! $private->put($path, $contents)) {
                        throw new \RuntimeException('Cannot write private copy.');
                    }
                }
                if (! hash_equals(hash('sha256', $contents), hash('sha256', $private->get($path)))) {
                    throw new \RuntimeException('Private copy differs; public original preserved.');
                }
                if (! $public->delete($path)) {
                    throw new \RuntimeException('Cannot remove public copy.');
                }
                $moved++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error($path.': '.$exception->getMessage());
            }
        }

        $this->info("Moved: {$moved}; failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
