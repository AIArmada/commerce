<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;

interface MembershipHook
{
    public function onMemberAdded(Model $subject, Model $user, MemberRole $role): void;

    public function onMemberRemoved(Model $subject, Model $user, ?MemberRole $previousRole): void;

    public function onMemberRoleChanged(Model $subject, Model $user, MemberRole $oldRole, MemberRole $newRole): void;
}
