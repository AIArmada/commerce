<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\RemoveMemberAction;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RemoveOrganizationMemberAction
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private RemoveMemberAction $action,
    ) {}

    public function handle(Organization $organization, Model $actor, Model $member): void
    {
        DB::transaction(function () use ($organization, $actor, $member): void {
            $organization = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $organization, 'organization.manage-members');

            $resolved = $organization->members()->whereKey($member->getKey())->first();

            if ($resolved === null || ! $resolved->is($member)) {
                throw new InvalidArgumentException('The member must use the organization member model.');
            }

            $this->action->handle($organization, $resolved);
        });
    }
}
