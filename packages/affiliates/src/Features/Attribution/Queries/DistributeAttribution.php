<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use AIArmada\Affiliates\Data\AttributionTouch;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionDistribution;
use AIArmada\Affiliates\Features\Attribution\Extensions\LastTouchAttribution;
use Illuminate\Container\Attributes\Tag;

final class DistributeAttribution implements AttributionDistribution
{
    /** @var array<string, AttributionStrategy> */
    private array $strategiesByKey = [];

    /** @param iterable<AttributionStrategy> $strategies */
    public function __construct(
        #[Tag('affiliates.attribution_strategy')]
        iterable $strategies = [],
    ) {
        foreach ($strategies as $strategy) {
            $this->strategiesByKey[$strategy->key()] = $strategy;
        }
    }

    private function resolve(?string $key = null): AttributionStrategy
    {
        $model = $key ?? config('affiliates.tracking.attribution_model', 'last_touch');

        if (isset($this->strategiesByKey[$model])) {
            return $this->strategiesByKey[$model];
        }

        return $this->strategiesByKey['last_touch'] ?? new LastTouchAttribution;
    }

    /**
     * @param  list<AttributionTouch>  $touches
     * @return array<string, float>
     */
    public function distribute(array $touches, ?string $strategy = null): array
    {
        return $this->resolve($strategy)->distribute($touches);
    }
}
