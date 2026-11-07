<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\Authz\Support\UserWildcardPermissions;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

it('invalidates authorization verdicts after schema-qualified revocations', function (bool $configureSchema): void {
    $user = User::query()->create([
        'name' => 'Schema Cache User',
        'email' => 'schema-cache@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'Schema Cache Role', 'guard_name' => 'web']);
    $user->assignRole($role);
    $user->givePermissionTo(Permission::findOrCreate('schema-cache.*', 'web'));

    if ($configureSchema) {
        foreach (['model_has_roles', 'model_has_permissions'] as $key) {
            $table = config("permission.table_names.{$key}");
            config()->set("permission.table_names.{$key}", 'main.' . $table);
        }
    }

    expect(UserRoleChecker::hasGlobalRole($user, 'Schema Cache Role'))->toBeTrue()
        ->and(UserWildcardPermissions::for($user))->toBe(['schema-cache.*']);

    foreach (['model_has_roles', 'model_has_permissions'] as $key) {
        $table = config("permission.table_names.{$key}");
        DB::table($configureSchema ? $table : 'main.' . $table)
            ->where('model_id', $user->getKey())->delete();
    }

    expect(UserRoleChecker::hasGlobalRole($user, 'Schema Cache Role'))->toBeFalse()
        ->and(UserWildcardPermissions::for($user))->toBe([]);
})->with([false, true]);

it('invalidates authorization verdicts after insert-or-ignore grants', function (): void {
    $user = User::query()->create([
        'name' => 'Insert Cache User',
        'email' => 'insert-cache@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'Insert Cache Role', 'guard_name' => 'web']);
    $permission = Permission::findOrCreate('insert-cache.*', 'web');
    $assignment = [
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
        'team_id' => getPermissionsTeamId(),
    ];

    expect(UserRoleChecker::hasGlobalRole($user, 'Insert Cache Role'))->toBeFalse()
        ->and(UserWildcardPermissions::for($user))->toBe([]);

    DB::table(config('permission.table_names.model_has_roles'))
        ->insertOrIgnore(['role_id' => $role->getKey(), ...$assignment]);

    expect(UserRoleChecker::hasGlobalRole($user, 'Insert Cache Role'))->toBeTrue()
        ->and(UserWildcardPermissions::for($user))->toBe([]);

    DB::table(config('permission.table_names.model_has_permissions'))
        ->insertOrIgnore(['permission_id' => $permission->getKey(), ...$assignment]);

    expect(UserWildcardPermissions::for($user))->toBe(['insert-cache.*']);
});

it('caches wildcard permissions for eloquent users within a request', function (): void {
    $user = User::query()->create([
        'name' => 'Wildcard Cache User',
        'email' => 'wildcard-cache-user@example.com',
        'password' => 'secret',
    ]);

    $permission = Permission::create([
        'name' => 'orders.*',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(UserWildcardPermissions::for($user))->toBe(['orders.*']);

    $queriesAfterFirstCheck = $queries;

    expect($queriesAfterFirstCheck)->toBeGreaterThan(0)
        ->and(UserWildcardPermissions::for($user))->toBe(['orders.*'])
        ->and($queries)->toBe($queriesAfterFirstCheck);

    $user->revokePermissionTo($permission);

    expect(UserWildcardPermissions::for($user))->toBe([]);
});

it('caches global role verdicts by model identity across instances', function (): void {
    $first = User::query()->create([
        'name' => 'Global Role Cache User',
        'email' => 'global-role-cache-user@example.com',
        'password' => 'secret',
    ]);

    $role = Role::create([
        'name' => 'Super Admin Cache',
        'guard_name' => 'web',
    ]);

    $first->assignRole($role);

    $second = User::query()->findOrFail($first->getKey());

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(UserRoleChecker::hasGlobalRole($first, 'Super Admin Cache'))->toBeTrue();

    $queriesAfterFirstCheck = $queries;

    expect($queriesAfterFirstCheck)->toBeGreaterThan(0)
        ->and(UserRoleChecker::hasGlobalRole($second, 'Super Admin Cache'))->toBeTrue()
        ->and($queries)->toBe($queriesAfterFirstCheck);

    $first->removeRole($role);

    expect(UserRoleChecker::hasGlobalRole($second, 'Super Admin Cache'))->toBeFalse();
});

it('does not reuse authorization verdicts from a rolled back grant', function (): void {
    $user = User::query()->create([
        'name' => 'Rollback User',
        'email' => 'rollback-cache@example.com',
        'password' => 'secret',
    ]);
    $permission = Permission::findOrCreate('rollback.*', 'web');
    $role = Role::create(['name' => 'Rollback Role', 'guard_name' => 'web']);

    DB::beginTransaction();

    try {
        $user->givePermissionTo($permission);
        $user->assignRole($role);

        expect(UserWildcardPermissions::for($user))->toBe(['rollback.*'])
            ->and(UserRoleChecker::hasGlobalRole($user, 'Rollback Role'))->toBeTrue();
    } finally {
        DB::rollBack();
    }

    expect(UserWildcardPermissions::for($user))->toBe([])
        ->and(UserRoleChecker::hasGlobalRole($user, 'Rollback Role'))->toBeFalse();
});

it('keeps wildcard permissions distinct when registrar team mode changes', function (): void {
    $user = User::query()->create([
        'name' => 'Team Mode User',
        'email' => 'team-mode-cache@example.com',
        'password' => 'secret',
    ]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->teams = true;
    setPermissionsTeamId('11111111-1111-4111-8111-111111111111');
    $user->givePermissionTo(Permission::findOrCreate('team-mode.*', 'web'));
    setPermissionsTeamId('22222222-2222-4222-8222-222222222222');

    try {
        expect(UserWildcardPermissions::for($user))->toBe([]);

        $registrar->teams = false;

        expect(UserWildcardPermissions::for($user))->toBe(['team-mode.*']);

        $registrar->teams = true;

        expect(UserWildcardPermissions::for($user))->toBe([]);
    } finally {
        $registrar->teams = true;
        setPermissionsTeamId(null);
    }
});

it('resets authorization caches before the next queue job', function (): void {
    $user = User::query()->create([
        'name' => 'Queue Boundary User',
        'email' => 'queue-cache@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'Queue Boundary Role', 'guard_name' => 'web']);
    $user->assignRole($role);
    $user->givePermissionTo(Permission::findOrCreate('queue-boundary.*', 'web'));

    expect(UserRoleChecker::hasGlobalRole($user, 'Queue Boundary Role'))->toBeTrue()
        ->and(UserWildcardPermissions::for($user))->toBe(['queue-boundary.*']);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    event(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

    expect(UserRoleChecker::hasGlobalRole($user, 'Queue Boundary Role'))->toBeTrue()
        ->and(UserWildcardPermissions::for($user))->toBe(['queue-boundary.*'])
        ->and($queries)->toBeGreaterThan(0);
});

it('isolates identical user identities on different database connections', function (): void {
    $user = User::query()->create([
        'name' => 'Connection Boundary User',
        'email' => 'connection-cache@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'Connection Role', 'guard_name' => 'web']);
    $user->assignRole($role);
    $user->givePermissionTo(Permission::findOrCreate('connection.*', 'web'));

    config()->set('database.connections.authz_cache_isolation', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $isolated = DB::connection('authz_cache_isolation');
    $tables = [(new User)->getTable(), ...array_values(config('permission.table_names'))];

    try {
        foreach (array_unique($tables) as $table) {
            $definition = DB::selectOne('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);
            $isolated->statement($definition->sql);
        }
        $isolated->table($user->getTable())->insert($user->getAttributes());
        $other = User::on('authz_cache_isolation')->findOrFail($user->getKey());

        expect(UserRoleChecker::hasGlobalRole($user, 'Connection Role'))->toBeTrue()
            ->and(UserWildcardPermissions::for($user))->toBe(['connection.*'])
            ->and(UserRoleChecker::hasGlobalRole($other, 'Connection Role'))->toBeFalse()
            ->and(UserWildcardPermissions::for($other))->toBe([]);
    } finally {
        DB::purge('authz_cache_isolation');
    }
});
