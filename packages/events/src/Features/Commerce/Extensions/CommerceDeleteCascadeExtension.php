<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Commerce\Actions\DeleteCommerceScopeDependents;

final class CommerceDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteCommerceScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}
