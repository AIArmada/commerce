<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RemoveMemberAction
{
    use AsAction;

    public function handle(Model $subject, Model $user): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        /** @phpstan-ignore method.notFound */
        $member = $subject->members()->whereKey($user->getKey())->first();

        if ($member === null) {
            return;
        }

        /** @phpstan-ignore property.notFound */
        $role = MemberRole::fromSpatieRoleName((string) $member->pivot?->role);

        DB::transaction(function () use ($role, $subject, $user): void {
            /** @phpstan-ignore method.notFound */
            $subject->members()->detach($user->getKey());

            if ($role !== null) {
                app(MembershipRoleSyncService::class)->revokeFromUser($subject, $user, $role);
            }
        });

        if (app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberRemoved($subject, $user, $role);
        }
    }
}
