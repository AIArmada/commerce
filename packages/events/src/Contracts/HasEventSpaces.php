<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventSpaces
{
    public function eventSpaces(): iterable;

    public function availableEventSpaceTypes(): iterable;
}
