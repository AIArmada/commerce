<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Queries\MembershipIdentityMapper;
use AIArmada\Membership\Queries\MembershipMembersQuery;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RemoveMemberAction
{
    use AsAction;

    public function __construct(
        private readonly MembershipMembersQuery $members,
        private readonly MembershipSubjectGuard $subjectGuard,
        private readonly SyncMembershipRolesAction $roleSync,
    ) {}

    public function handle(Model $subject, Model $user): void
    {
        $this->subjectGuard->validate($subject);

        $role = null;
        $removed = DB::transaction(function () use ($subject, $user, &$role): bool {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $member = $this->members->find($subject, $user);

            if ($member === null) {
                return false;
            }

            $role = MemberRole::fromSpatieRoleName((string) $this->members->role($member));

            if ($subject instanceof MembershipMutationGuard) {
                $subject->assertMemberCanBeRemoved(MembershipIdentityMapper::fromModel($member), $role?->value);
            }

            $this->members->relation($subject)->detach($user->getKey());

            if ($role !== null) {
                $this->roleSync->revokeFromUser($subject, $user, $role);
            }

            return true;
        });

        if ($removed && app()->bound(MembershipHook::class)) {
            $subjectIdentity = MembershipIdentityMapper::fromModel($subject);
            $userIdentity = MembershipIdentityMapper::fromModel($user);

            DB::afterCommit(static function () use ($subjectIdentity, $userIdentity, $role): void {
                app(MembershipHook::class)->onMemberRemoved($subjectIdentity, $userIdentity, $role?->value);
            });
        }
    }
}
