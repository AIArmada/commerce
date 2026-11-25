<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Extensions;

use AIArmada\Events\Contracts\EventScopeCloner;
use AIArmada\Events\Features\Registrations\Actions\CloneRegistrationsScopeAction;

final class RegistrationsCloneScopeExtension implements EventScopeCloner
{
    public function __construct(
        private readonly CloneRegistrationsScopeAction $cloneScopeAction,
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
