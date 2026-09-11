<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
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

it('uses the organizations membership table config as the single source of truth', function (): void {
    expect((new Organization)->membersTable())
        ->toBe(config('organizations.database.tables.members'));
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

it('reports duplicate organization slugs without deleting them during preflight', function (): void {
    $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
    $slug = 'preflight-organization-' . Str::lower(Str::random(8));
    $now = now();

    Schema::table($organizationsTable, function (Blueprint $table): void {
        $table->dropUnique('organizations_slug_unique');
    });

    DB::table($organizationsTable)->insert([
        [
            'id' => (string) Str::uuid(),
            'name' => 'Preflight Organization One',
            'slug' => $slug,
            'status' => 'active',
            'visibility' => 'private',
            'created_by' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Preflight Organization Two',
            'slug' => $slug,
            'status' => 'active',
            'visibility' => 'private',
            'created_by' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $migration = require dirname(__DIR__, 4) . '/packages/organizations/database/migrations/2026_09_07_074034_add_organization_integrity_indexes.php';
    $before = DB::table($organizationsTable)->where('slug', $slug)->count();

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'No rows were deleted')
        ->and(DB::table($organizationsTable)->where('slug', $slug)->count())
        ->toBe($before);
});

it('reports duplicate memberships without deleting them during preflight', function (): void {
    $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
    $membersTable = (string) config('organizations.database.tables.members', 'organization_members');
    $organizationId = (string) Str::uuid();
    $userId = (string) Str::uuid();
    $now = now();

    DB::table($organizationsTable)->insert([
        'id' => $organizationId,
        'name' => 'Membership Preflight Organization',
        'slug' => 'membership-preflight-' . Str::lower(Str::random(8)),
        'status' => 'active',
        'visibility' => 'private',
        'created_by' => (string) Str::uuid(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Schema::table($membersTable, function (Blueprint $table): void {
        $table->dropUnique('organization_members_organization_user_unique');
    });

    DB::table($membersTable)->insert([
        [
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => MemberRole::Admin->spatieRoleName(),
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => MemberRole::Admin->spatieRoleName(),
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $migration = require dirname(__DIR__, 4) . '/packages/organizations/database/migrations/2026_09_07_074034_add_organization_integrity_indexes.php';
    $before = DB::table($membersTable)
        ->where('organization_id', $organizationId)
        ->where('user_id', $userId)
        ->count();

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'No rows were deleted')
        ->and(DB::table($membersTable)
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->count())
        ->toBe($before);
});
