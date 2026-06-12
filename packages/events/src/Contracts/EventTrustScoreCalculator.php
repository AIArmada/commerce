<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventTrustScoreCalculator
{
    public function score(mixed $target): int | float;
}
