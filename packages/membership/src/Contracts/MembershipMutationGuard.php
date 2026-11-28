<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Data\MembershipIdentity;

/**
 * Allows a membership subject to enforce invariants around membership writes.
 *
 * Implementations are optional. The membership package remains generic while
 * domain aggregates can protect ownership and other subject-specific rules.
 */
interface MembershipMutationGuard
{
    public function assertMemberCanBeAdded(MembershipIdentity $member, string $role, ?string $existingRole): void;

    public function assertMemberCanBeRemoved(MembershipIdentity $member, ?string $currentRole): void;

    public function assertMemberRoleCanChange(MembershipIdentity $member, string $role, ?string $currentRole): void;
}
