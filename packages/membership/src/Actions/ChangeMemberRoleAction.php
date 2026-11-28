<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ChangeMemberRoleAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        $oldRole = null;

        DB::transaction(function () use ($role, $subject, $user, &$oldRole): void {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            /** @phpstan-ignore method.notFound */
            $freshMember = $subject->members()->whereKey($user->getKey())->first();

            if ($freshMember === null) {
                throw new RuntimeException('Cannot change the role of a non-member.');
            }

            if ($subject instanceof MembershipMutationGuard) {
                $subject->assertMemberRoleCanChange($freshMember, $role);
            }

            /** @phpstan-ignore property.notFound */
            $oldRole = MemberRole::fromSpatieRoleName((string) $freshMember->pivot?->role);

            AddMemberAction::make()->handle($subject, $user, $role, notifyAdded: false);
        });

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
