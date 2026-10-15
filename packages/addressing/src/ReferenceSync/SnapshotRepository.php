<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** Batched, restart-safe snapshots. Scope is intentionally limited to canonical reference data. */
final class SnapshotRepository
{
    public const TABLE = 'addressing_reference_snapshots';

    /** Capture the application's current reference data, including manually edited values. */
    public function captureLocal(): int
    {
        $generation = (string) Str::uuid();
        $rows = [];
        $count = 0;
        (new ReferenceTypes)->each(function (string $type, array $key, array $payload) use (&$rows, &$count, $generation): void {
            $rows[] = $this->row('local', $type, $key, $payload, $generation);
            if (count($rows) >= 500) {
                $this->upsert($rows);
                $count += count($rows);
                $rows = [];
            }
        });
        if ($rows !== []) {
            $this->upsert($rows);
            $count += count($rows);
        }
        DB::table(self::TABLE)->where('scope', 'local')->where('generation', '!=', $generation)->delete();
        $this->set('last_capture', ['at' => now()->toIso8601String(), 'records' => $count]);
        return $count;
    }

    /** Replace the complete remote scope using a verified release archive. */
    public function receiveRemote(iterable $records, string $revision): int
    {
        $generation = (string) Str::uuid();
        $rows = [];
        $count = 0;
        foreach ($records as $record) {
            $rows[] = $this->row('remote', $record['entity_type'], $record['natural_key'], $record['payload'], $generation, $revision);
            if (count($rows) >= 500) {
                $this->upsert($rows);
                $count += count($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            $this->upsert($rows);
            $count += count($rows);
        }
        // Only remove old-generation entries after all new rows have been committed.
        DB::table(self::TABLE)->where('scope', 'remote')->where('generation', '!=', $generation)->delete();
        $this->set('remote_revision', $revision);
        return $count;
    }

    /** Must only be called against a proven pristine installation or matching historical baseline. */
    public function initializeBaseline(string $revision): void
    {
        if (DB::table(self::TABLE)->where('scope', 'baseline')->exists()) {
            throw new LogicException('Baseline already exists. Initialization cannot overwrite history.');
        }
        if (DB::table(self::TABLE)->where('scope', 'remote')->doesntExist()) {
            throw new LogicException('A verified remote dataset must be loaded first.');
        }
        $this->captureLocal();
        $bad = DB::table(self::TABLE . ' as l')
            ->leftJoin(self::TABLE . ' as r', function ($j): void {
                $j->on('r.entity_type', '=', 'l.entity_type')->on('r.key_hash', '=', 'l.key_hash')->where('r.scope', '=', 'remote');
            })->where('l.scope', 'local')
            ->where(function ($q): void { $q->whereNull('r.id')->orWhereColumn('l.checksum', '!=', 'r.checksum'); })
            ->exists();
        $missing = DB::table(self::TABLE . ' as r')
            ->leftJoin(self::TABLE . ' as l', function ($j): void {
                $j->on('l.entity_type', '=', 'r.entity_type')->on('l.key_hash', '=', 'r.key_hash')->where('l.scope', '=', 'local');
            })->where('r.scope', 'remote')->whereNull('l.id')->exists();
        if ($bad || $missing) {
            throw new LogicException('Local database differs from the declared canonical release. Bootstrap with its historical baseline; NEVER adopt the modified database as baseline.');
        }
        // Snapshot copy is atomic and does not modify application reference tables.
        DB::transaction(function () use ($revision): void {
            DB::table(self::TABLE)->where('scope', 'remote')->orderBy('id')->chunkById(500, function ($rows): void {
                $data = [];
                foreach ($rows as $r) {
                    $data[] = [
                        'scope' => 'baseline', 'entity_type' => $r->entity_type,
                        'key_hash' => $r->key_hash, 'natural_key' => $r->natural_key,
                        'checksum' => $r->checksum, 'payload' => $r->payload, 'revision' => $r->revision,
                        'generation' => $r->generation,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
                DB::table(self::TABLE)->insert($data);
            });
            $this->set('baseline_revision', $revision);
        });
    }

    /** Reconcile just one safe, successfully applied record. Conflicts keep their original baseline. */
    public function acceptRemote(string $type, string $keyHash): void
    {
        $remote = DB::table(self::TABLE)->where(['scope' => 'remote', 'entity_type' => $type, 'key_hash' => $keyHash])->first();
        if ($remote === null) {
            DB::table(self::TABLE)->where(['scope' => 'baseline', 'entity_type' => $type, 'key_hash' => $keyHash])->delete();
            return;
        }
        $this->upsert([[
            'scope' => 'baseline', 'entity_type' => $remote->entity_type,
            'key_hash' => $remote->key_hash, 'natural_key' => $remote->natural_key,
            'checksum' => $remote->checksum, 'payload' => $remote->payload, 'revision' => $remote->revision,
            'generation' => $remote->generation,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = DB::table('addressing_reference_settings')->where('key', $key)->value('value');
        return $stored === null ? $default : json_decode((string) $stored, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('addressing_reference_settings')->updateOrInsert(['key' => $key], [
            'value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }

    public function row(string $scope, string $type, array $key, array $payload, string $generation, ?string $revision = null): array
    {
        if (! in_array($type, ReferenceTypes::TYPES, true)) {
            throw new \InvalidArgumentException('Not a permitted canonical reference entity.');
        }
        $keyJson = Canonical::json($key);
        return [
            'scope' => $scope, 'entity_type' => $type,
            'key_hash' => hash('sha256', $keyJson), 'natural_key' => $keyJson,
            'checksum' => Canonical::checksum($payload), 'payload' => Canonical::json($payload), 'revision' => $revision,
            'generation' => $generation, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function upsert(array $rows): void
    {
        DB::table(self::TABLE)->upsert($rows, ['scope', 'entity_type', 'key_hash'], ['natural_key', 'checksum', 'payload', 'revision', 'generation', 'updated_at']);
    }
}
