<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Extensions;

use AIArmada\Events\Contracts\EventScopeCloner;
use AIArmada\Events\Features\Venues\Actions\CloneVenuesScopeAction;

final class VenuesCloneScopeExtension implements EventScopeCloner
{
    public function __construct(
        private readonly CloneVenuesScopeAction $cloneScopeAction,
    ) {}

    public function cloneForScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void {
        $this->cloneScopeAction->cloneForScope(
            $sourceScopeType,
            $sourceScopeId,
            $targetScopeType,
            $targetScopeId,
            $options,
        );
    }
}
