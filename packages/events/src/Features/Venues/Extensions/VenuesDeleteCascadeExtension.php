<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Venues\Actions\DeleteVenuesScopeDependents;

final class VenuesDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteVenuesScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId);
    }
}
