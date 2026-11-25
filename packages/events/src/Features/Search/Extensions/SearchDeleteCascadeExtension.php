<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Search\Actions\DeleteSearchScopeDependents;

final class SearchDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteSearchScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            return;
        }

        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}
