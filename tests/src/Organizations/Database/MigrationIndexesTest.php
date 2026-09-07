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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(OrganizationsTestCase::class);

it('adds organization uniqueness and membership lookup indexes', function (): void {
    $organizationsTable = config('organizations.database.tables.organizations', 'organizations');
    $membersTable = config('organizations.database.tables.members', 'organization_members');

    expect(Schema::hasIndex($organizationsTable, 'organizations_slug_unique'))->toBeTrue()
        ->and(Schema::hasIndex($membersTable, 'organization_members_organization_user_unique'))->toBeTrue()
        ->and(Schema::hasIndex($membersTable, 'organization_members_organization_role_index'))->toBeTrue();
});

it('enforces unique organization slugs at the database boundary', function (): void {
    $attributes = [
        'name' => 'First Organization',
        'slug' => 'duplicate-organization',
        'created_by' => (string) Str::uuid(),
    ];

    Organization::query()->create($attributes);

    expect(fn () => Organization::query()->create([
        ...$attributes,
        'name' => 'Second Organization',
    ]))->toThrow(QueryException::class);
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
