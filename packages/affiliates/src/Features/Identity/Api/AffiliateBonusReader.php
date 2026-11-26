<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;

interface AffiliateBonusReader
{
    /** @return iterable<AffiliateBonusCandidate> */
    public function candidates(bool $includeGlobal): iterable;

    /** @return iterable<AffiliateBonusCandidate> */
    public function recruiters(CarbonImmutable $from, CarbonImmutable $to, int $minimum, bool $includeGlobal): iterable;
}
