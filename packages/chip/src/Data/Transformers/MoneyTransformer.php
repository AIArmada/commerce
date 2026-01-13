<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data\Transformers;

use Akaunting\Money\Money;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transform Money objects back to integer cents for CHIP API serialization.
 */
final class MoneyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): int
    {
        if (! $value instanceof Money) {
            return 0;
        }

        return (int) $value->getAmount();
    }
}
