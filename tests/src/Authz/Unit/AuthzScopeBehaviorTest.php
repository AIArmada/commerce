<?php

declare(strict_types=1);

use AIArmada\Authz\Concerns\HasAuthzScope;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\AuthzScope;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('removes scoped roles and assignments when an authz scope is deleted', function (): void {
    $scopeTable = (new AuthzScope)->getTable();

    Schema::dropIfExists($scopeTable);
    Schema::create($scopeTable, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('scopeable_type');
        $table->uuid('scopeable_id');
        $table->string('label')->nullable();
        $table->timestamps();
    });

    $globalRole = Role::create([
        'name' => 'global-role',
        'guard_name' => 'web',
    ]);
    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'test-scope',
        'scopeable_id' => '11111111-1111-4111-8111-111111111111',
        'label' => 'Test Scope',
    ]);

    setPermissionsTeamId($scope->getKey());

    $scopedRole = Role::create([
        'name' => 'scoped-role',
        'guard_name' => 'web',
    ]);
    $permission = Permission::findOrCreate('scoped.view', 'web');
    $scopedRole->givePermissionTo($permission);

    $user = User::query()->create([
        'name' => 'Scoped User',
        'email' => 'scoped-user@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole($scopedRole);
    $user->givePermissionTo($permission);

    setPermissionsTeamId(null);
    $globalAssignmentUser = User::query()->create([
        'name' => 'Global Assignment User',
        'email' => 'global-assignment-user@example.com',
        'password' => 'secret',
    ]);
    $globalAssignmentUser->assignRole($scopedRole);

    $scope->delete();

    expect(DB::table('roles')->where('id', $globalRole->getKey())->exists())->toBeTrue()
        ->and(DB::table('roles')->where('id', $scopedRole->getKey())->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('role_id', $scopedRole->getKey())->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->where('team_id', $scope->getKey())->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('role_id', $scopedRole->getKey())->exists())->toBeFalse();
});

it('removes scoped roles and assignments when a scopeable model is deleted', function (): void {
    Schema::dropIfExists('authz_scopes');
    Schema::create('authz_scopes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('scopeable_type');
        $table->uuid('scopeable_id');
        $table->string('label')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('authz_scopeables');
    Schema::create('authz_scopeables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $scopeable = AuthzScopeableFixture::query()->create(['name' => 'Scopeable']);
    $scope = $scopeable->ensureAuthzScope();

    setPermissionsTeamId($scope->getKey());

    $role = Role::create([
        'name' => 'scopeable-role',
        'guard_name' => 'web',
    ]);
    $permission = Permission::findOrCreate('scopeable.view', 'web');
    $role->givePermissionTo($permission);

    $user = User::query()->create([
        'name' => 'Scopeable User',
        'email' => 'scopeable-user@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permission);

    setPermissionsTeamId(null);
    $scopeable->delete();

    expect(AuthzScope::query()->whereKey($scope->getKey())->exists())->toBeFalse()
        ->and(DB::table('roles')->where('id', $role->getKey())->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('role_id', $role->getKey())->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->where('team_id', $scope->getKey())->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('role_id', $role->getKey())->exists())->toBeFalse();
});

final class AuthzScopeableFixture extends Model
{
    use HasAuthzScope;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['name'];

    public function getTable(): string
    {
        return 'authz_scopeables';
    }
}
