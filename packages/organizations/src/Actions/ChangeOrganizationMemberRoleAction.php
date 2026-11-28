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

            $this->action->handle($organization, $member, $role);
        });
    }
}
