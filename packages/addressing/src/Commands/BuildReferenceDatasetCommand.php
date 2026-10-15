<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Commands;

use AIArmada\Addressing\ReferenceSync\ReferenceArchive;
use Illuminate\Console\Command;

/** CI/release engineering only: build from an isolated, freshly seeded reference DB. */
final class BuildReferenceDatasetCommand extends Command
{
    protected $signature = 'address:reference-build {revision : Immutable package dataset revision} {--output= : Output ZIP path}';

    protected $description = 'Build canonical addressing data archive from a pristine seeded database';

    public function handle(ReferenceArchive $archive): int
    {
        $revision = (string) $this->argument('revision');
        if (! preg_match('/^[a-zA-Z0-9._-]{1,80}$/', $revision)) {
            $this->error('Revision must be a short immutable identifier.');
            return self::FAILURE;
        }
        $output = $this->option('output') ?: dirname(__DIR__, 2) . '/resources/datasets/current.zip';
        $count = $archive->exportCanonical($output, $revision);
        $this->info("Wrote {$count} records to {$output}");
        $this->warn('IMPORTANT: this output is canonical only if the build DB was freshly seeded and contains no application edits.');
        return self::SUCCESS;
    }
}
