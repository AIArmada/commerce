<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventScopeCascadeDeleter
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void;
}
