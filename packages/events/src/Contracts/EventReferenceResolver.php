<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventReference;

interface EventReferenceResolver
{
    public function resolve(EventReference $reference): array;
}
