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

final class AddMemberAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        /** @phpstan-ignore method.notFound */
        $existingMember = $subject->members()->whereKey($user->getKey())->first();
        /** @phpstan-ignore property.notFound */
        $existingRole = $existingMember?->pivot?->role;

        DB::transaction(function () use ($existingRole, $role, $subject, $user): void {
            /** @phpstan-ignore method.notFound */
            $subject->members()->syncWithoutDetaching([
                $user->getKey() => [
                    'role' => $role->spatieRoleName(),
                    'joined_at' => now(),
                ],
            ]);

            $oldRole = is_string($existingRole)
                ? MemberRole::fromSpatieRoleName($existingRole)
                : null;

            if ($oldRole !== null && $oldRole !== $role) {
                app(MembershipRoleSyncService::class)->revokeFromUser($subject, $user, $oldRole);
            }

            app(MembershipRoleSyncService::class)->assignToUser($subject, $user, $role);
        });

        if (app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded($subject, $user, $role);
        }
    }
}
