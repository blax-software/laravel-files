<?php

namespace Blax\Files\Console;

use Blax\Files\Models\File;
use Illuminate\Console\Command;

class CleanupOrphanedFilesCommand extends Command
{
    protected $signature = 'files:cleanup
                            {--days=30 : Remove orphaned files older than N days}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove files that are not attached to any model';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $query = File::orphaned()
            ->where('created_at', '<', now()->subDays($days));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] Would delete' : 'Deleting') . " {$count} orphaned file(s) older than {$days} days…");

        if (! $dryRun) {
            $query->each(function (File $file) {
                $file->delete();
            });

            $this->info("Deleted {$count} file(s).");
        }

        return self::SUCCESS;
    }
}
