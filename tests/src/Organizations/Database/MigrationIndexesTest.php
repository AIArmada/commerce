<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(OrganizationsTestCase::class);

it('uses the organizations membership table config as the single source of truth', function (): void {
    expect((new Organization)->membersTable())
        ->toBe(config('organizations.database.tables.members'));
});

it('enforces unique organization slugs at the database boundary', function (): void {
    $makeRow = static function (string $name): Organization {
        $organization = new Organization([
            'name' => $name,
            'slug' => 'duplicate-organization',
        ]);
        $organization->forceFill(['created_by' => (string) Str::uuid()]);
        $organization->save();

        return $organization;
    };

    $makeRow('First Organization');

    expect(fn () => $makeRow('Second Organization'))->toThrow(QueryException::class);
});

it('enforces one membership row per organization and user at the database boundary', function (): void {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Membership Organization']);

    AddMemberAction::make()->handle($organization, $member, MemberRole::Admin);

    expect(fn () => DB::table($organization->membersTable())->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->getKey(),
        'user_id' => $member->getKey(),
        'role' => MemberRole::Admin->spatieRoleName(),
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
