<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class InviteOrganizationMemberAction
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private InviteMemberAction $action,
    ) {}

    public function handle(Organization $organization, Model $actor, string $recipient, MemberRole $role, ?string $channel = null): MembershipInvitation
    {
        return DB::transaction(function () use ($organization, $actor, $recipient, $role, $channel): MembershipInvitation {
            $organization = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $organization, 'organization.manage-members');

            if ($role === MemberRole::Owner) {
                throw new AuthorizationException('Use the ownership transfer workflow to assign an owner.');
            }

            return $this->action->handle($organization, $recipient, $role, $actor, null, $channel);
        });
    }
}
