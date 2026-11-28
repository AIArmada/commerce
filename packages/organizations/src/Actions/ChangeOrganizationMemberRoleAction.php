<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\ChangeMemberRoleAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ChangeOrganizationMemberRoleAction
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private ChangeMemberRoleAction $action,
    ) {}

    public function handle(Organization $organization, Model $actor, Model $member, MemberRole $role): void
    {
        DB::transaction(function () use ($organization, $actor, $member, $role): void {
            $organization = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $organization, 'organization.manage-members');

            if ($role === MemberRole::Owner) {
                throw new AuthorizationException('Use the ownership transfer workflow to assign an owner.');
            }

            $resolved = $organization->members()->whereKey($member->getKey())->first();

            if ($resolved === null || ! $resolved->is($member)) {
                throw new InvalidArgumentException('The member must use the organization member model.');
            }

            $this->action->handle($organization, $resolved, $role);
        });
    }
}
