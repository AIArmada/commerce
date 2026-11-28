<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class AddOrganizationMemberAction
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private AddMemberAction $action,
    ) {}

    public function handle(Organization $organization, Model $actor, Model | string $member, MemberRole $role): void
    {
        DB::transaction(function () use ($organization, $actor, $member, $role): void {
            $organization = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $organization, 'organization.manage-members');

            if ($role === MemberRole::Owner) {
                throw new AuthorizationException('Use the ownership transfer workflow to assign an owner.');
            }

            $query = $organization->members()->getRelated()->newQuery();
            $resolved = is_string($member)
                ? $query->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim($member))])->first()
                : $query->whereKey($member->getKey())->first();

            if ($member instanceof Model && ($resolved === null || ! $resolved->is($member))) {
                throw new InvalidArgumentException('The member must use the organization member model.');
            }

            if ($resolved === null) {
                throw ValidationException::withMessages(['email' => 'Unable to add this member.']);
            }

            $this->action->handle($organization, $resolved, $role);
        });
    }
}
