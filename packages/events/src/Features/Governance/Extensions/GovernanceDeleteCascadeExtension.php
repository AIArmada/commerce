<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Governance\Actions\DeleteGovernanceScopeDependents;

final class GovernanceDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteGovernanceScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}
