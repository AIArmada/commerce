<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\RelationManagers\AbstractMemberInvitationsRelationManager;
use AIArmada\FilamentOrganizations\RelationManagers\AbstractMembersRelationManager;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        public bool $allowAdd = false;

        protected function authorizeMutation(string $ability): void
        {
            if (! $this->allowAdd || $ability !== 'add') {
                parent::authorizeMutation($ability);
            }
        }

        protected function addMember(Model $owner, Model $member, string $role): void {}

        protected function changeMemberRole(Model $owner, Model $member, string $role): void {}

        protected function removeMember(Model $owner, Model $member): void {}

        public function probeSearch(string $term): array
        {
            return $this->searchUsers($term);
        }

        public function probeOptionLabel(mixed $value): ?string
        {
            return $this->getUserOptionLabel($value);
        }

        public function probeRoleOptions(): array
        {
            return $this->roleOptions();
        }
    };

    expect(fn () => $membersRm->probeSearch('100%_'))->toThrow(HttpException::class)
        ->and(fn () => $membersRm->probeOptionLabel('unknown'))->toThrow(HttpException::class);

    $membersRm->allowAdd = true;

    $results = $membersRm->probeSearch('100%_');
    expect($results)->toHaveCount(1)
        ->and(array_values($results)[0])->toContain('100%_Special User')
        ->and($membersRm->probeRoleOptions())->toHaveKey(MemberRole::Owner->value);

    $invitesRm = new class extends AbstractMemberInvitationsRelationManager
    {
        public function probeAuthorization(Model $owner): void
        {
            $this->authorizeInvitationMutation('invite', $owner);
        }

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
    };

    expect($invitesRm)->toBeInstanceOf(AbstractMemberInvitationsRelationManager::class)
        ->and(fn () => $invitesRm->probeAuthorization(new User))->toThrow(HttpException::class);
});
