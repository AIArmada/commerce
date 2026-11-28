<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\RevokeInvitationAction;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class RevokeOrganizationInvitationAction
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private RevokeInvitationAction $action,
    ) {}

    public function handle(Organization $organization, Model $actor, MembershipInvitation $invitation): void
    {
        DB::transaction(function () use ($organization, $actor, $invitation): void {
            $organization = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $organization, 'organization.manage-members');

            $invitation = $organization->invitations()->whereKey($invitation->getKey())->firstOrFail();

            $this->action->handle($invitation, $actor);
        });
    }
}
