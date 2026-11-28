<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Data\MembershipIdentity;

/**
 * Observes committed membership mutations. Use MembershipMutationGuard for
 * synchronous invariant checks before a mutation is persisted.
 */
interface MembershipHook
{
    public function onMemberAdded(MembershipIdentity $subject, MembershipIdentity $user, string $role): void;

    public function onMemberRemoved(MembershipIdentity $subject, MembershipIdentity $user, ?string $previousRole): void;

    public function onMemberRoleChanged(MembershipIdentity $subject, MembershipIdentity $user, string $oldRole, string $newRole): void;
}
