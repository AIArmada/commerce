<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Commands;

use AIArmada\Addressing\ReferenceSync\Canonical;
use AIArmada\Addressing\ReferenceSync\ReferenceArchive;
use AIArmada\Addressing\ReferenceSync\ReferenceTypes;
use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

/** Maintenance-facing validator; NEVER mutates canonical providers or imported applications. */
final class InspectReferenceContributionCommand extends Command
{
    protected $signature = 'address:reference-inspect {archive : Production contribution ZIP} {--report= : Write machine-readable review report}';

    protected $description = 'Validate an untrusted contribution ZIP and list proposed source-data changes';

    public function handle(ReferenceArchive $archives): int
    {
        $path = (string) $this->argument('archive');
        $manifest = $archives->inspect($path, 'contributions');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open contribution ZIP.');
        }
        $handle = $zip->getStream('records.ndjson');
        if (! is_resource($handle)) {
            $zip->close();

            throw new RuntimeException('Missing contributions stream.');
        }
        $counts = ['create' => 0, 'update' => 0, 'delete' => 0];
        $candidates = [];
        $digest = hash_init('sha256');

        try {
            while (($line = fgets($handle, 4_194_305)) !== false) {
                if (mb_strlen($line) > 4_194_304 || ! str_ends_with($line, "\n")) {
                    throw new RuntimeException('Invalid or oversized contribution row.');
                }
                hash_update($digest, $line);
                $row = Canonical::decode(mb_trim($line));
                $type = $row['entity_type'] ?? null;
                $op = $row['operation'] ?? null;
                $before = $row['before'] ?? null;
                $after = $row['after'] ?? null;
                if (! in_array($type, ReferenceTypes::TYPES, true)
                    || ! in_array($op, ['create', 'update', 'delete'], true)
                    || ! is_array($row['natural_key'] ?? null)
                    || (! is_array($before) && $before !== null)
                    || (! is_array($after) && $after !== null)) {
                    throw new RuntimeException('Malformed contribution entry.');
                }
                if (($op === 'create' && $after === null)
                    || ($op === 'update' && ($before === null || $after === null))
                    || ($op === 'delete' && $before === null)) {
                    throw new RuntimeException('Contribution operation has missing before/after data.');
                }
                $counts[$op]++;
                if (count($candidates) >= 100000) {
                    throw new RuntimeException('Contribution archive exceeds review candidate limit.');
                }
                $candidates[] = $row;
            }
        } finally {
            fclose($handle);
            $zip->close();
        }
        if (! hash_equals((string) $manifest['sha256'], hash_final($digest))
            || count($candidates) !== (int) $manifest['count']) {
            throw new RuntimeException('Contribution archive checksum/count mismatch.');
        }
        $this->info('Validated contribution batch: ' . ($manifest['batch_id'] ?? 'unknown'));
        $this->line('Baseline revision: ' . $manifest['revision']);
        $this->table(['Operation', 'Candidates'], array_map(
            static fn (string $op, int $count): array => [$op, $count],
            array_keys($counts),
            array_values($counts),
        ));
        $report = $this->option('report');
        if (is_string($report) && $report !== '') {
            $json = json_encode([
                'manifest' => $manifest, 'counts' => $counts, 'candidates' => $candidates,
                'notice' => 'Validation only. Compare EACH before value with current canonical source; inspect conflicts and modify provider/CSV data in a PR.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($report, $json, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write review report.');
            }
            $this->info('Wrote review report to ' . $report);
        }

        return self::SUCCESS;
    }
}
