<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventScopeCloner
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneForScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void;
}
