<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class DefaultOrganizationAuthorization implements OrganizationAuthorization
{
    public function authorize(Model $actor, Organization $organization, string $ability): void
    {
        $member = $organization->members()->whereKey($actor->getKey())->first();

        if (! $member instanceof Model) {
            throw new AuthorizationException('The actor is not a member of this organization.');
        }

        $role = MemberRole::fromSpatieRoleName((string) data_get($member->getRelationValue('pivot'), 'role'));

        if ($role === null || ! $this->roleCan($role, $ability)) {
            throw new AuthorizationException(sprintf('The organization member cannot perform "%s".', $ability));
        }
    }

    private function roleCan(MemberRole $role, string $ability): bool
    {
        return match ($ability) {
            'organization.view' => true,
            'organization.update', 'organization.manage-members', 'organization.change-status', 'organization.change-visibility' => in_array($role, [MemberRole::Owner, MemberRole::Admin], true),
            'organization.transfer-ownership' => $role === MemberRole::Owner,
            default => in_array($role, [MemberRole::Owner, MemberRole::Admin], true),
        };
    }
}
