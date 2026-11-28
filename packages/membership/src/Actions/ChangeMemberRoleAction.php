<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Queries\MembershipMembersQuery;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ChangeMemberRoleAction
{
    use AsAction;

    public function __construct(
        private readonly MembershipMembersQuery $members,
        private readonly MembershipSubjectGuard $subjectGuard,
        private readonly AddMemberAction $addMember,
    ) {}

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        $this->subjectGuard->validate($subject);

        $oldRole = null;

        DB::transaction(function () use ($role, $subject, $user, &$oldRole): void {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $freshMember = $this->members->find($subject, $user);

            if ($freshMember === null) {
                throw new RuntimeException('Cannot change the role of a non-member.');
            }

            if ($subject instanceof MembershipMutationGuard) {
                $subject->assertMemberRoleCanChange($freshMember, $role);
            }

            $oldRole = MemberRole::fromSpatieRoleName((string) $this->members->role($freshMember));

            $this->addMember->handleResolvedMember($subject, $user, $role, $freshMember, false);
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
