<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Jobs;

use AIArmada\Addressing\ReferenceSync\DiffEngine;
use AIArmada\Addressing\ReferenceSync\ReferenceArchive;
use AIArmada\Addressing\ReferenceSync\ReferenceWriter;
use AIArmada\Addressing\ReferenceSync\SnapshotRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/** Run multi-million-row operations off the Filament HTTP lifecycle. */
final class ReferenceSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public readonly string $operation) {}

    public function handle(): void
    {
        $lock = Cache::lock('addressing:reference-sync', 7500);
        if (! $lock->get()) {
            throw new RuntimeException('Another reference synchronization operation is running.');
        }
        $repo = new SnapshotRepository;
        try {
            $repo->set('job', ['status' => 'running', 'operation' => $this->operation, 'started' => now()->toIso8601String()]);
            $result = match ($this->operation) {
                'compare' => $this->compare(),
                'load' => $this->loadPackaged(),
                'baseline' => $this->bootstrap(),
                'apply' => (new ReferenceWriter)->applyApproved(100),
                default => throw new RuntimeException('Unknown reference synchronization operation.'),
            };
            $repo->set('job', ['status' => 'complete', 'operation' => $this->operation,
                'completed' => now()->toIso8601String(), 'result' => $result]);
        } catch (Throwable $e) {
            $repo->set('job', ['status' => 'failed', 'operation' => $this->operation,
                'completed' => now()->toIso8601String(), 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function compare(): array
    {
        $count = (new SnapshotRepository)->captureLocal();
        return ['rows_scanned' => $count, ...((new DiffEngine)->rebuild())];
    }

    private function loadPackaged(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/datasets/current.zip';
        if (! is_file($path)) {
            throw new RuntimeException('The installed Composer package lacks resources/datasets/current.zip.');
        }
        $count = (new ReferenceArchive)->importCanonical($path);
        return ['imported_remote' => $count, ...$this->compare()];
    }

    private function bootstrap(): array
    {
        $repo = new SnapshotRepository;
        $revision = (string) $repo->get('remote_revision', '');
        if ($revision === '') {
            throw new RuntimeException('Load the packaged reference dataset first.');
        }
        $repo->initializeBaseline($revision);
        return ['initialized_baseline' => $revision, ...$this->compare()];
    }
}
