<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Registrations\Actions\DeleteRegistrationsScopeDependents;

final class RegistrationsDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteRegistrationsScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            return;
        }

        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}
