<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Allows a membership subject to enforce invariants around membership writes.
 *
 * Implementations are optional. The membership package remains generic while
 * domain aggregates can protect ownership and other subject-specific rules.
 */
interface MembershipMutationGuard
{
    public function assertMemberCanBeAdded(Model $member, MemberRole $role, ?Model $existingMember): void;

    public function assertMemberCanBeRemoved(Model $member): void;

    public function assertMemberRoleCanChange(Model $member, MemberRole $role): void;
}
