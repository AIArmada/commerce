<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ChangeMemberRoleAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        /** @phpstan-ignore method.notFound */
        $member = $subject->members()->whereKey($user->getKey())->first();

        if ($member === null) {
            throw new RuntimeException('Cannot change the role of a non-member.');
        }

        /** @phpstan-ignore property.notFound */
        $oldRole = MemberRole::fromSpatieRoleName((string) $member->pivot?->role);

        AddMemberAction::make()->handle($subject, $user, $role);

        if ($oldRole !== null && $oldRole !== $role && app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberRoleChanged(
                $subject,
                $user,
                $oldRole,
                $role,
            );
        }
    }
}
