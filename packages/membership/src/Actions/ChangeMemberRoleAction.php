<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ChangeMemberRoleAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        /** @phpstan-ignore method.notFound */
        $member = $subject->members()->whereKey($user->getKey())->first();

        if ($member === null) {
            throw new RuntimeException('Cannot change the role of a non-member.');
        }

        if ($subject instanceof MembershipMutationGuard) {
            $subject->assertMemberRoleCanChange($member, $role);
        }

        /** @phpstan-ignore property.notFound */
        $oldRole = MemberRole::fromSpatieRoleName((string) $member->pivot?->role);

        AddMemberAction::make()->handleResolvedMember($subject, $user, $role, $member);

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
