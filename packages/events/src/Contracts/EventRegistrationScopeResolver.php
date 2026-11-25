<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventRegistrationScopeData;

interface EventRegistrationScopeResolver
{
    public function resolve(string $targetType, string $targetId): EventRegistrationScopeData;
}
