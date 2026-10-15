<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/** Canonical bundles ship with Composer; untrusted contribution ZIPs NEVER get applied directly. */
final class ReferenceArchive
{
    private const MAX_RECORD_BYTES = 4_194_304;

    private const MAX_ARCHIVE_BYTES = 2_147_483_648;

    /** Build a reference-only canonical release on a PRISTINE package-seeded database. */
    public function exportCanonical(string $path, string $revision): int
    {
        (new SnapshotRepository)->captureLocal();

        return $this->writeArchive($path, 'canonical', $revision, function ($handle): int {
            $count = 0;
            DB::table(SnapshotRepository::TABLE)->where('scope', 'local')->orderBy('id')
                ->chunkById(1000, function ($rows) use ($handle, &$count): void {
                    foreach ($rows as $r) {
                        fwrite($handle, json_encode([
                            'entity_type' => $r->entity_type,
                            'natural_key' => json_decode($r->natural_key, true, 512, JSON_THROW_ON_ERROR),
                            'payload' => json_decode($r->payload, true, 512, JSON_THROW_ON_ERROR),
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                        $count++;
                    }
                });

            return $count;
        });
    }

    /** Only reviewed entries are promoted; 'conflict' always requires separate resolution. */
    public function exportContributions(string $path): int
    {
        $repo = new SnapshotRepository;
        $revision = (string) $repo->get('baseline_revision', '');
        if ($revision === '') {
            throw new RuntimeException('Canonical baseline not initialized; cannot export trustworthy changes.');
        }

        return $this->writeArchive($path, 'contributions', $revision, function ($handle): int {
            $count = 0;
            DB::table(DiffEngine::TABLE)->where('direction', 'contribution')
                ->where('decision', 'approved')->where('operation', '!=', 'conflict')
                ->orderBy('id')->chunkById(1000, function ($rows) use ($handle, &$count): void {
                    foreach ($rows as $r) {
                        fwrite($handle, json_encode([
                            'entity_type' => $r->entity_type, 'natural_key' => Canonical::decode($r->natural_key),
                            'operation' => $r->operation,
                            'before' => Canonical::decode($r->baseline_payload),
                            'before_revision' => $r->baseline_revision,
                            'after' => Canonical::decode($r->local_payload),
                            'base_checksum' => $r->baseline_payload === null ? null : hash('sha256', $r->baseline_payload),
                            'fingerprint' => $r->fingerprint, 'notes' => $r->notes,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                        $count++;
                    }
                });

            return $count;
        });
    }

    /** Verify the archive before changing DB state; the actual import then runs transactionally. */
    public function importCanonical(string $path, ?string $expectedRevision = null): int
    {
        $manifest = $this->inspect($path, 'canonical');
        if ($expectedRevision !== null && $manifest['revision'] !== $expectedRevision) {
            throw new RuntimeException('Installed package dataset revision does not match the expected revision.');
        }
        $this->verifyRecords($path, $manifest);

        return DB::transaction(function () use ($path, $manifest): int {
            return (new SnapshotRepository)->receiveRemote($this->records($path), $manifest['revision']);
        });
    }

    public function inspect(string $path, string $requiredKind): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Unable to open reference archive.');
        }

        try {
            if ($zip->numFiles !== 2 || $zip->locateName('manifest.json') === false || $zip->locateName('records.ndjson') === false) {
                throw new RuntimeException('Archive must contain only manifest.json and records.ndjson.');
            }
            $manifestRaw = $zip->getFromName('manifest.json');
            $stat = $zip->statName('records.ndjson');
            if (! is_string($manifestRaw) || mb_strlen($manifestRaw) > 65536 || ! is_array($stat)
                || ($stat['size'] ?? 0) > self::MAX_ARCHIVE_BYTES) {
                throw new RuntimeException('Invalid reference archive size.');
            }
            $manifest = Canonical::decode($manifestRaw);
            if (($manifest['schema'] ?? null) !== 1 || ($manifest['kind'] ?? null) !== $requiredKind
                || ! is_string($manifest['revision'] ?? null) || $manifest['revision'] === ''
                || ! is_string($manifest['sha256'] ?? null) || ! ctype_xdigit($manifest['sha256'])
                || mb_strlen($manifest['sha256']) !== 64 || ! is_int($manifest['count'] ?? null)) {
                throw new RuntimeException('Invalid archive manifest.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function verifyRecords(string $path, array $manifest): void
    {
        $sha = hash_init('sha256');
        $count = 0;
        foreach ($this->lines($path) as $line) {
            hash_update($sha, $line);
            $data = Canonical::decode(mb_rtrim($line, "\r\n"));
            if (! in_array($data['entity_type'] ?? '', ReferenceTypes::TYPES, true)
                || ! is_array($data['natural_key'] ?? null) || ! is_array($data['payload'] ?? null)) {
                throw new RuntimeException('Invalid canonical record.');
            }
            $count++;
            if ($count > 5_000_000) {
                throw new RuntimeException('Record limit exceeded.');
            }
        }
        if (! hash_equals($manifest['sha256'], hash_final($sha)) || $count !== $manifest['count']) {
            throw new RuntimeException('Canonical archive checksum or record count mismatch.');
        }
    }

    private function records(string $path): Generator
    {
        foreach ($this->lines($path) as $line) {
            yield Canonical::decode(mb_rtrim($line, "\r\n"));
        }
    }

    private function lines(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open ZIP.');
        }
        $stream = $zip->getStream('records.ndjson');
        if (! is_resource($stream)) {
            $zip->close();

            throw new RuntimeException('Missing records stream.');
        }

        try {
            while (($line = fgets($stream, self::MAX_RECORD_BYTES + 1)) !== false) {
                if (mb_strlen($line) > self::MAX_RECORD_BYTES || ! str_ends_with($line, "\n")) {
                    throw new RuntimeException('Canonical record is too large or incomplete.');
                }
                yield $line;
            }
        } finally {
            fclose($stream);
            $zip->close();
        }
    }

    private function writeArchive(string $path, string $kind, string $revision, callable $produce): int
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ext-zip is required.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'addressing_');
        if ($tmp === false) {
            throw new RuntimeException('Cannot allocate export file.');
        }
        $handle = fopen($tmp, 'wb');

        try {
            $count = $produce($handle);
            fclose($handle);
            $handle = null;
            if ($count === 0 && $kind === 'contributions') {
                throw new RuntimeException('No approved contributions are ready for export.');
            }
            $sha = hash_file('sha256', $tmp);
            $batchId = (string) Str::uuid();
            $manifest = [
                'batch_id' => $batchId,
                'schema' => 1, 'kind' => $kind, 'revision' => $revision,
                'count' => $count, 'sha256' => $sha, 'created_at' => now()->toIso8601String(),
            ];
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0750, true) && ! is_dir(dirname($path))) {
                throw new RuntimeException('Unable to create archive directory.');
            }
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to write archive ZIP.');
            }
            $zip->addFile($tmp, 'records.ndjson');
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize archive ZIP.');
            }
            if ($kind === 'contributions') {
                DB::table('addressing_reference_exports')->insert([
                    'id' => $batchId, 'kind' => $kind,
                    'baseline_revision' => $revision,
                    'sha256' => hash_file('sha256', $path),
                    'record_count' => $count, 'status' => 'exported',
                    'exported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $count;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($tmp);
        }
    }
}
