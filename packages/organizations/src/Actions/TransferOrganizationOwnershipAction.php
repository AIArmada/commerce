<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class TransferOrganizationOwnershipAction
{
    use AsAction;

    public function handle(Organization $organization, Model $actor, Model $newOwner): Organization
    {
        app(OrganizationAuthorization::class)->authorize($actor, $organization, 'organization.transfer-ownership');

        if ($actor->is($newOwner)) {
            return DB::transaction(function () use ($organization): Organization {
                $owners = $organization->ownerMember()->lockForUpdate()->get();

                if ($owners->count() !== 1) {
                    throw new RuntimeException('Organization ownership invariant violated: exactly one owner must exist.');
                }

                return $organization;
            });
        }

        return DB::transaction(function () use ($actor, $newOwner, $organization): Organization {
            $owners = $organization->ownerMember()->lockForUpdate()->get();
            $target = $organization->members()->lockForUpdate()->whereKey($newOwner->getKey())->first();

            if ($owners->count() !== 1) {
                throw new RuntimeException('Organization ownership invariant violated: exactly one owner must exist.');
            }

            $currentOwner = $owners->first();

            if (! $target instanceof Model) {
                throw new RuntimeException('The new owner must already be an organization member.');
            }

            $targetRole = MemberRole::fromSpatieRoleName((string) data_get($target->getRelationValue('pivot'), 'role'));

            if ($currentOwner->is($target)) {
                return $organization;
            }

            $members = $organization->members();
            $members->updateExistingPivot($currentOwner->getKey(), ['role' => MemberRole::Admin->spatieRoleName()]);
            $members->updateExistingPivot($target->getKey(), ['role' => MemberRole::Owner->spatieRoleName()]);

            $roleSync = app(MembershipRoleSyncService::class);
            $roleSync->revokeFromUser($organization, $currentOwner, MemberRole::Owner);
            $roleSync->assignToUser($organization, $currentOwner, MemberRole::Admin);

            if ($targetRole !== null && $targetRole !== MemberRole::Owner) {
                $roleSync->revokeFromUser($organization, $target, $targetRole);
            }

            $roleSync->assignToUser($organization, $target, MemberRole::Owner);

            app(OrganizationLifecycleHook::class)->ownershipTransferred($organization, $currentOwner, $target, $actor);

            return $organization->fresh();
        });
    }
}
