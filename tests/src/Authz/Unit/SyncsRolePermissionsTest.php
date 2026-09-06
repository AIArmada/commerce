<?php

declare(strict_types=1);

use AIArmada\Authz\Concerns\SyncsRolePermissions;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

it('syncs permissions that already exist for the role guard', function (): void {
    $role = Role::create([
        'name' => 'permission-sync-role',
        'guard_name' => 'web',
    ]);
    $permission = Permission::create([
        'name' => 'permission-sync.view',
        'guard_name' => 'web',
    ]);

    $sync = new SyncsRolePermissionsFixture($role);
    $sync->extract(['permissions_direct' => [$permission->name]]);
    $sync->sync();

    expect($role->permissions()->pluck('name')->all())->toBe([$permission->name]);
});

it('rejects unknown permissions without creating them', function (): void {
    $role = Role::create([
        'name' => 'unknown-permission-role',
        'guard_name' => 'web',
    ]);

    $sync = new SyncsRolePermissionsFixture($role);
    $sync->extract(['permissions_direct' => ['permission-sync.missing']]);

    expect(fn (): mixed => $sync->sync())
        ->toThrow(PermissionDoesNotExist::class);

    expect(Permission::query()
        ->where('name', 'permission-sync.missing')
        ->where('guard_name', 'web')
        ->exists())->toBeFalse();
});

final class SyncsRolePermissionsFixture
{
    use SyncsRolePermissions;

    public function __construct(public Role $record) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extract(array $data): array
    {
        return $this->extractPermissionIds($data);
    }

    public function sync(): void
    {
        $this->syncPermissionsToRole();
    }
}
