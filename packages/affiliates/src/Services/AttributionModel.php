<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use AIArmada\Affiliates\Data\AttributionTouch;
use AIArmada\Affiliates\Strategies\LastTouchAttribution;
use Illuminate\Container\Attributes\Tag;

final class AttributionModel
{
    private array $strategiesByKey = [];

    public function __construct(
        #[Tag('affiliates.attribution_strategy')]
        iterable $strategies = [],
    ) {
        foreach ($strategies as $strategy) {
            $this->strategiesByKey[$strategy->key()] = $strategy;
        }
    }

    public function strategies(): array
    {
        return array_values($this->strategiesByKey);
    }

    public function resolve(?string $key = null): AttributionStrategy
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
    public function distribute(array $touches): array
    {
        return $this->resolve()->distribute($touches);
    }
}
