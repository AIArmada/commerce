<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Contracts\EventAuthzDriver;
use AIArmada\Events\Data\EventAssignmentData;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;

final class SyncManagementAssignmentToAuthzAction
{
    public function __construct(
        private readonly EventAuthzDriver $authz,
    ) {}

    public function handle(EventManagementAssignment $assignment): void
    {
        $this->authz->syncAssignment(new EventAssignmentData(
            manageableType: (string) $assignment->manageable_type,
            manageableId: (string) $assignment->manageable_id,
            managerType: (string) $assignment->manager_type,
            managerId: (string) $assignment->manager_id,
            role: (string) $assignment->role,
        ));
    }
}
