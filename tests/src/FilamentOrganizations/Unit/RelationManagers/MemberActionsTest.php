<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers\MembersRelationManager;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use Filament\Tables\Table;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(FilamentOrganizationsTestCase::class);

function memberAction(string $name, MembersRelationManager $manager): mixed
{
    $table = $manager->table(Table::make($manager));

    $action = collect($table->getHeaderActions())
        ->firstWhere(fn ($candidate): bool => $candidate->getName() === $name)
        ?? collect($table->getActions())
            ->firstWhere(fn ($candidate): bool => $candidate->getName() === $name);

    expect($action)->not->toBeNull();

    return $action->livewire($manager);
}

function memberOrganization(): array
{
    $owner = User::factory()->create();
    $org = CreateOrganizationAction::make()->handle($owner, ['name' => 'Member Actions Org']);

    test()->actingAs($owner);

    $manager = new MembersRelationManager;
    $manager->ownerRecord = $org;

    return [$org, $owner, $manager];
}

it('adds a member through the relation action', function (): void {
    [$org, $owner, $manager] = memberOrganization();
    $candidate = User::factory()->create();

    memberAction('addMember', $manager)->call([
        'data' => ['email' => $candidate->email, 'role' => MemberRole::Viewer->value],
    ]);

    expect($org->members()->whereKey($candidate->getKey())->exists())->toBeTrue();
});

it('matches member emails case-insensitively', function (): void {
    [$org, $owner, $manager] = memberOrganization();
    $candidate = User::factory()->create();

    memberAction('addMember', $manager)->call([
        'data' => ['email' => '  ' . mb_strtoupper($candidate->email) . '  ', 'role' => MemberRole::Viewer->value],
    ]);

    expect($org->members()->whereKey($candidate->getKey())->exists())->toBeTrue();
});

it('reports unknown member emails generically', function (): void {
    [$org, $owner, $manager] = memberOrganization();

    try {
        memberAction('addMember', $manager)->call([
            'data' => ['email' => 'nobody-knows-this@example.com', 'role' => MemberRole::Viewer->value],
        ]);

        $this->fail('Expected an HttpException for an unknown email.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getMessage())->toBe('Unable to add this member.');
    }
});

it('changes and removes members through the row actions', function (): void {
    [$org, $owner, $manager] = memberOrganization();
    $member = User::factory()->create();

    memberAction('addMember', $manager)->call([
        'data' => ['email' => $member->email, 'role' => MemberRole::Viewer->value],
    ]);

    $row = $org->members()->whereKey($member->getKey())->firstOrFail();

    memberAction('changeRole', $manager)->record($row)->call([
        'data' => ['role' => MemberRole::Editor->value],
    ]);

    expect((string) $org->members()->whereKey($member->getKey())->firstOrFail()->getRelationValue('pivot')->role)
        ->toBe(MemberRole::Editor->spatieRoleName());

    memberAction('remove', $manager)->record($row->fresh())->call();

    expect($org->members()->whereKey($member->getKey())->exists())->toBeFalse();
});
