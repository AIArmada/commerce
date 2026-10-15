<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Three-way comparisons: baseline = installed truth, local = app truth, remote = latest package truth. */
final class DiffEngine
{
    public const TABLE = 'addressing_reference_changes';

    /** Rebuilds review queues, retaining decisions ONLY while their exact fingerprint is unchanged. */
    public function rebuild(): array
    {
        $generation = (string) Str::uuid();
        $counts = ['contribution' => 0, 'delivery' => 0, 'conflict' => 0];
        // Visit each reference key exactly once, including additions in either branch.
        foreach (['baseline', 'local', 'remote'] as $anchor) {
            $a = substr($anchor, 0, 1);
            $others = array_values(array_diff(['baseline', 'local', 'remote'], [$anchor]));
            $b = substr($others[0], 0, 1);
            $c = substr($others[1], 0, 1);
            $query = DB::table(SnapshotRepository::TABLE . " as {$a}")
                ->where("{$a}.scope", $anchor)
                ->leftJoin(SnapshotRepository::TABLE . " as {$b}", function ($join) use ($a, $b, $others): void {
                    $join->on("{$a}.entity_type", '=', "{$b}.entity_type")
                        ->on("{$a}.key_hash", '=', "{$b}.key_hash")->where("{$b}.scope", '=', $others[0]);
                })
                ->leftJoin(SnapshotRepository::TABLE . " as {$c}", function ($join) use ($a, $c, $others): void {
                    $join->on("{$a}.entity_type", '=', "{$c}.entity_type")
                        ->on("{$a}.key_hash", '=', "{$c}.key_hash")->where("{$c}.scope", '=', $others[1]);
                })
                ->select("{$a}.id as anchor_id", "{$a}.entity_type", "{$a}.key_hash", "{$a}.natural_key")
                ->addSelect("{$a}.payload as {$anchor}_payload", "{$a}.checksum as {$anchor}_checksum", "{$a}.revision as {$anchor}_revision")
                ->addSelect("{$b}.payload as {$others[0]}_payload", "{$b}.checksum as {$others[0]}_checksum", "{$b}.revision as {$others[0]}_revision")
                ->addSelect("{$c}.payload as {$others[1]}_payload", "{$c}.checksum as {$others[1]}_checksum", "{$c}.revision as {$others[1]}_revision");
            // Avoid reprocessing: baseline wins; local only when baseline is absent; remote only when both absent.
            if ($anchor !== 'baseline') {
                $baselineAlias = $anchor === 'local' ? $b : $c; // when local, others=[baseline,remote]; when remote, others=[baseline,local]
                $query->whereNull("{$baselineAlias}.id");
            }
            if ($anchor === 'remote') {
                $localAlias = $b === 'l' ? $b : $c;
                $query->whereNull("{$localAlias}.id");
            }
            $query->orderBy("{$a}.id")->chunkById(400, function ($rows) use ($generation, &$counts): void {
                foreach ($rows as $r) {
                    $b = $r->baseline_checksum ?? null;
                    $l = $r->local_checksum ?? null;
                    $u = $r->remote_checksum ?? null;
                    $localDiff = $b !== $l;
                    $remoteDiff = $b !== $u;
                    $diverged = $localDiff && $remoteDiff && $l !== $u;
                    if ($localDiff && $l !== $u) {
                        $operation = $diverged ? 'conflict' : ($b === null ? 'create' : ($l === null ? 'delete' : 'update'));
                        $this->record('contribution', $r, $operation, $generation);
                        $counts['contribution']++;
                    }
                    if ($remoteDiff) {
                        $operation = $diverged ? 'conflict' : ($l === $u ? 'reconciled' : ($u === null ? 'retire' : ($b === null ? 'create' : 'update')));
                        $this->record('delivery', $r, $operation, $generation);
                        $counts['delivery']++;
                    }
                    if ($diverged) {
                        $counts['conflict']++;
                    }
                }
            }, "{$a}.id", 'anchor_id');
        }
        // Sweep stale rows after scanning using explicit scan_generation column.
        DB::table(self::TABLE)->where('scan_generation', '!=', $generation)->delete();
        return $counts;
    }

    private function record(string $direction, object $r, string $operation, string $generation): void
    {
        $baseline = $r->baseline_payload ?? null;
        $local = $r->local_payload ?? null;
        $remote = $r->remote_payload ?? null;
        $fingerprint = hash('sha256', Canonical::json([
            'direction' => $direction, 'operation' => $operation, 'base' => $baseline,
            'local' => $local, 'remote' => $remote,
        ]));
        $where = ['direction' => $direction, 'entity_type' => $r->entity_type, 'key_hash' => $r->key_hash];
        $current = DB::table(self::TABLE)->where($where)->first();
        $stillSame = $current !== null && hash_equals((string) $current->fingerprint, $fingerprint);
        DB::table(self::TABLE)->updateOrInsert($where, [
            'natural_key' => $r->natural_key, 'operation' => $operation,
            'decision' => $stillSame ? $current->decision : 'pending',
            'fingerprint' => $fingerprint,
            'baseline_payload' => $baseline, 'baseline_revision' => $r->baseline_revision ?? null, 'local_payload' => $local,
            'remote_payload' => $remote, 'scan_generation' => $generation,
            'notes' => $stillSame ? $current->notes : null,
            'reviewed_by' => $stillSame ? $current->reviewed_by : null,
            'reviewed_at' => $stillSame ? $current->reviewed_at : null,
            'updated_at' => now(),
        ]);
    }
}
