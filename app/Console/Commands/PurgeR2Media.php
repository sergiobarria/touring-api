<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class PurgeR2Media extends Command
{
    protected $signature = 'r2:purge-media
        {--execute : Delete the bucket objects and matching media records}
        {--force : Skip the interactive confirmation when executing}';

    protected $description = 'Dry-run or purge all objects from the development R2 bucket and its media records';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('The R2 media purge is restricted to local and testing environments.');

            return self::FAILURE;
        }

        try {
            $disk = Storage::disk('r2');
            $files = collect($disk->allFiles());
            $bytes = $files->sum(fn (string $path): int => $disk->size($path));
            $mediaRows = $this->mediaQuery()->count();
        } catch (Throwable $exception) {
            $this->error('Unable to inspect the R2 bucket: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Bucket', 'Objects', 'Size', 'Media rows'],
            [[
                (string) (config('filesystems.disks.r2.bucket') ?: '(not configured)'),
                $files->count(),
                Number::fileSize($bytes),
                $mediaRows,
            ]],
        );

        if (! $this->option('execute')) {
            $this->info('Dry run only. Pass --execute to perform the purge.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm('Delete every object in this R2 bucket and all matching media rows?')) {
            $this->info('Purge cancelled.');

            return self::SUCCESS;
        }

        try {
            if ($files->isNotEmpty() && ! $disk->delete($files->all())) {
                $this->error('R2 reported that one or more objects could not be deleted.');

                return self::FAILURE;
            }

            $remainingFiles = $disk->allFiles();

            if ($remainingFiles !== []) {
                $this->error(count($remainingFiles).' R2 object(s) remain. Media rows were not deleted; rerun the command safely.');

                return self::FAILURE;
            }

            $deletedRows = $this->mediaQuery()->delete();
        } catch (Throwable $exception) {
            $this->error('Unable to complete the R2 purge: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Purged {$files->count()} object(s) and {$deletedRows} media row(s).");

        return self::SUCCESS;
    }

    /** @return Builder<Media> */
    private function mediaQuery(): Builder
    {
        return Media::query()->where(function ($query): void {
            $query
                ->where('disk', 'r2')
                ->orWhere('conversions_disk', 'r2');
        });
    }
}
