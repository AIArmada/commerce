<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

/**
 * Tainted source-key boundary for the corrections sweep and recheck job.
 *
 * Core reverses awards for explicit business keys; enumerating which keys
 * are tainted (source corrections, confirmed fraud) is host work. Hosts
 * bind an implementation that reads their own correction/fraud stores.
 * The default binding yields no keys, so the sweep no-ops with a warning
 * instead of scanning a table core does not own.
 */
interface RewardCorrectionKeySource
{
    /**
     * @return list<array{business_key: string, reason: string, evidence: array<string, mixed>}>
     */
    public function taintedKeys(int $limit): array;

    /**
     * Single-key lookup for the recheck job: corrections recorded without
     * full attribution rows have no readable fact, but the key is still
     * tainted and its awards must settle.
     *
     * @return ?array{reason: string, evidence: array<string, mixed>}
     */
    public function causeFor(string $businessKey): ?array;
}
