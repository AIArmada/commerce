<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventTimeExpressionData;
use DateTimeInterface;

interface ResolvesEventTimeExpression
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(EventTimeExpressionData $expression, array $context = []): ?DateTimeInterface;
}
