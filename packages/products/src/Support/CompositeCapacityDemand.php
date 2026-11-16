<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use InvalidArgumentException;

/**
 * One component's demand against an application-defined capacity bucket.
 *
 * resourceKey identifies the underlying shared pool; scopeKey isolates
 * independent windows/configurations of that pool. available is the pool's
 * currently available units, not a per-Product or per-component allocation.
 */
final readonly class CompositeCapacityDemand
{
    public function __construct(
        public string $resourceKey,
        public string $scopeKey,
        public int $unitsPerComponent = 1,
        public ?int $available = null,
    ) {
        if (mb_trim($this->resourceKey) === '') {
            throw new InvalidArgumentException('A capacity demand requires a non-empty resource key.');
        }

        if ($this->unitsPerComponent < 1) {
            throw new InvalidArgumentException('Capacity units per component must be positive.');
        }

        if ($this->available !== null && $this->available < 0) {
            throw new InvalidArgumentException('Available capacity cannot be negative.');
        }
    }
}
