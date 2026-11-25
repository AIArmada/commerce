<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Changes\Actions\DeleteChangesScopeDependents;

final class ChangesDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteChangesScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            return;
        }

        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}
