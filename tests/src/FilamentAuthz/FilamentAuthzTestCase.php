<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Support\AuthzScopeTeamResolver;
use DateInterval;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class FilamentAuthzTestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permission.teams', true);
        $app['config']->set('permission.team_resolver', AuthzScopeTeamResolver::class);
        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $app['config']->set('permission.column_names', [
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);
        $app['config']->set('permission.cache', [
            'key' => 'spatie.permission.cache',
            'store' => 'array',
            'expiration_time' => DateInterval::createFromDateString('24 hours'),
        ]);

        $app['config']->set('filament-authz.authz_scopes.enabled', true);
        $app['config']->set('filament-authz.scoped_to_tenant', true);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUpDatabase(): void
    {
        Schema::dropIfExists('authz_scopes');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('authz_scopes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scopeable_type');
            $table->uuid('scopeable_id');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['scopeable_type', 'scopeable_id']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->nullable();
            $table->index('team_id', 'roles_team_foreign_key_index');
            $table->string('name');
            $table->string('guard_name');
            $table->foreignUuid('parent_role_id')->nullable();
            $table->foreignUuid('template_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('level')->default(0);
            $table->json('metadata')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_assignable')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'name', 'guard_name']);
            $table->index('parent_role_id', 'roles_parent_role_id_index');
            $table->index('template_id', 'roles_template_id_index');
            $table->index('level', 'roles_level_index');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('team_id')->nullable();

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->index('team_id', 'model_has_permissions_team_foreign_key_index');
            $table->primary(['team_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('team_id')->nullable();

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->index('team_id', 'model_has_roles_team_foreign_key_index');
            $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
}
