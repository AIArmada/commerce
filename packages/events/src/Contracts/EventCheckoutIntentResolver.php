<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventCheckoutIntentResolver
{
    public function resolve(string $targetType, string $targetId, string $registrationId): mixed;
}
