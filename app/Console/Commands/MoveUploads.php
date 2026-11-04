<?php

namespace App\Console\Commands;

use App\Support\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies private uploads from one disk to another (e.g. this server to
 * Google Drive) before switching UPLOADS_DISK. Files already on the target
 * are skipped, so it can be re-run safely. Nothing is deleted.
 */
class MoveUploads extends Command
{
    protected $signature = 'jobauko:move-uploads
        {--from=local : Disk the files are on now}
        {--to= : Disk to copy them to (e.g. google)}
        {--dry-run : Only count what would be copied}';

    protected $description = 'Copy resumes and documents to another storage disk before switching UPLOADS_DISK';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');

        if ($to === '' || $to === $from) {
            $this->error('Choose a different target disk with --to (for example --to=google).');

            return self::FAILURE;
        }

        $source = Storage::disk($from);
        $target = Storage::disk($to);
        $copied = $skipped = $failed = 0;

        // On Windows, "Resumes" and the older "resumes" are the same folder; count each file once.
        $paths = collect(Uploads::FOLDERS)
            ->flatMap(fn (string $folder) => $source->allFiles($folder))
            ->unique(fn (string $path) => mb_strtolower($path));

        foreach ($paths as $path) {
            try {
                if ($target->fileExists($path)) {
                    $skipped++;

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $target->writeStream($path, $source->readStream($path));
                }
                $copied++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("Could not copy {$path}: {$e->getMessage()}");
            }
        }

        $verb = $this->option('dry-run') ? 'Would copy' : 'Copied';
        $this->info("{$verb} {$copied} file(s) from \"{$from}\" to \"{$to}\"; {$skipped} already there; {$failed} failed.");

        if ($failed === 0 && ! $this->option('dry-run')) {
            $this->line("Files on \"{$from}\" were left in place. Remove them once the site works with UPLOADS_DISK={$to}.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
