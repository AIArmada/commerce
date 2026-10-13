<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Support\RewardSourceFact;

/**
 * Truthful source-reader boundary for entitlement evaluation.
 *
 * A null return means "unknown source" and evaluation must refuse it. The
 * default null implementation always returns null; production share/claim
 * adapters arrive after accepted integrations. Tests may supply an explicit
 * fake reader, and must label those readings as fixture evidence — never as
 * source-integration proof.
 */
interface RewardSourceFactReader
{
    public function read(string $sourceBusinessKey): ?RewardSourceFact;
}
