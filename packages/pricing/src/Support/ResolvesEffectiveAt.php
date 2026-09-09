<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Throwable;

trait ResolvesEffectiveAt
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveEffectiveAt(array $context): CarbonImmutable
    {
        $effectiveAt = Arr::get($context, 'effective_at');

        if ($effectiveAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($effectiveAt);
        }

        if (is_int($effectiveAt)) {
            return CarbonImmutable::createFromTimestamp($effectiveAt);
        }

        if (is_string($effectiveAt) && $effectiveAt !== '') {
            try {
                return CarbonImmutable::parse($effectiveAt);
            } catch (Throwable) {
            }
        }

        return CarbonImmutable::now();
    }
}
