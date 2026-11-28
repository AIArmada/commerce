<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\RelationManagers\AbstractMemberInvitationsRelationManager;
use AIArmada\FilamentOrganizations\RelationManagers\AbstractMembersRelationManager;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

uses(FilamentOrganizationsTestCase::class);

test('abstract members and invitations relation managers expose reusable hooks and search escaping', function (): void {
    User::factory()->create([
        'name' => '100%_Special User',
        'email' => 'special_user@example.com',
    ]);
    User::factory()->create([
        'name' => 'Ordinary Person',
        'email' => 'ordinary@example.com',
    ]);

    $membersRm = new class extends AbstractMembersRelationManager
    {
        protected function addMember(Model $owner, Model $member, string $role): void {}

        protected function changeMemberRole(Model $owner, Model $member, string $role): void {}

        protected function removeMember(Model $owner, Model $member): void {}

        public bool $allowSearch = true;

        protected function authorizeMutation(string $ability): void
        {
            if (! $this->allowSearch) {
                throw new AuthorizationException('Member lookup forbidden.');
            }
        }

        public function probeLabel(mixed $id): ?string
        {
            return $this->getUserOptionLabel($id);
        }

        public function probeSearch(string $term): array
        {
            return $this->searchUsers($term);
        }

        public function probeRoleOptions(): array
        {
            return $this->roleOptions();
        }
    };

    $results = $membersRm->probeSearch('100%_');
    expect($results)->toHaveCount(1)
        ->and(array_values($results)[0])->toContain('100%_Special User')
        ->and($membersRm->probeRoleOptions())->toHaveKey(MemberRole::Owner->value);

    $membersRm->allowSearch = false;
    expect(fn () => $membersRm->probeSearch('ordinary'))->toThrow(AuthorizationException::class)
        ->and(fn () => $membersRm->probeLabel('unknown'))->toThrow(AuthorizationException::class);

    $invitesRm = new class extends AbstractMemberInvitationsRelationManager
    {
        protected function createInvitation(
            Model $owner,
            string $recipient,
            string $role,
            Model $actor,
            ?CarbonInterface $expiresAt,
            string $channel,
        ): void {}

        protected function revokeInvitation(
            Model $owner,
            MembershipInvitation $invitation,
            Model $actor,
        ): void {}

        protected function authorizeInvitationMutation(string $ability, Model $owner): void {}
    };

    expect($invitesRm)->toBeInstanceOf(AbstractMemberInvitationsRelationManager::class);
});
