<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentAuthz\Fixtures\AuthzUser;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config()->set('filament-authz.user_model', AuthzUser::class);
    config()->set('auth.defaults.guard', 'web');

    Schema::dropIfExists('authz_users');
    Schema::create('authz_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Filament::setCurrentPanel('admin');

    $this->actingAs(AuthzUser::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));
});

test('list page renders and shows permissions', function (): void {
    $permission = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    Livewire::test(ListPermissions::class)
        ->assertSuccessful()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('guard_name')
        ->assertCanSeeTableRecords([$permission]);
});

test('create page can create a permission', function (): void {
    Livewire::test(CreatePermission::class)
        ->assertSuccessful()
        ->fillForm([
            'name' => 'order.update',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $permission = Permission::query()->sole();

    expect($permission)
        ->name->toBe('order.update')
        ->guard_name->toBe('web');
});

test('edit page can update a permission', function (): void {
    $permission = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    Livewire::test(EditPermission::class, ['record' => $permission->getKey()])
        ->assertSuccessful()
        ->fillForm([
            'name' => 'order.read',
            'guard_name' => 'web',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($permission->refresh()->name)->toBe('order.read');
});
