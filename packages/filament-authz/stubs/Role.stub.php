<?php

declare(strict_types=1);

namespace Spatie\Permission\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Extended Role model with hierarchy support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $parent_role_id
 * @property string|null $template_id
 * @property string|null $description
 * @property int $level
 * @property array<string, mixed>|null $metadata
 * @property bool $is_system
 * @property bool $is_assignable
 * @property Collection<int, Permission> $permissions
 *
 * @method static static find(string|int $id)
 * @method static static findOrFail(string|int $id)
 * @method static static findByName(string $name, ?string $guardName = null)
 * @method static static findById(string|int $id, ?string $guardName = null)
 * @method static static findOrCreate(string $name, ?string $guardName = null)
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static Collection<int, static> hydrate(array $items)
 * @method bool save(array $options = [])
 * @method static refresh()
 * @method static syncPermissions(...$permissions)
 * @method static givePermissionTo(...$permissions)
 * @method static revokePermissionTo(...$permissions)
 * @method bool hasPermissionTo(string|\Spatie\Permission\Contracts\Permission $permission, ?string $guardName = null)
 * @method bool hasAnyPermission(...$permissions)
 * @method bool hasAllPermissions(...$permissions)
 */
class Role extends Model {}
