<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventTimeExpression;
use DateTimeInterface;

interface ResolvesEventTimeExpression
{
    public function resolve(EventTimeExpression $expression, array $context = []): ?DateTimeInterface;
}
