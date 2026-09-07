<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keeps model global scopes when loading global permission records', function (): void {
    config()->set('permission.models.permission', PermissionResourceScopedPermission::class);

    DB::table('permissions')->insert([
        [
            'id' => (string) Str::uuid(),
            'name' => 'web.permission',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'name' => 'api.permission',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(PermissionResource::getEloquentQuery()->orderBy('name')->pluck('name')->all())
        ->toBe(['web.permission']);
});

final class PermissionResourceScopedPermission extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('web_guard', static function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('guard_name'), 'web');
        });
    }

    public function getTable(): string
    {
        return 'permissions';
    }
}
