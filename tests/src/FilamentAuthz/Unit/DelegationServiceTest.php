<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\CannotDelegateException;
use AIArmada\FilamentAuthz\Services\DelegationService;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    // Configure user model
    config(['filament-authz.user_model' => User::class]);

    // Drop and recreate tables
    Schema::dropIfExists('authz_delegations');
    Schema::create('authz_delegations', function ($table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('delegator_id');
        $table->foreignUuid('delegatee_id');
        $table->string('permission');
        $table->timestamp('expires_at')->nullable();
        $table->boolean('can_redelegate')->default(false);
        $table->timestamp('revoked_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('authz_permission_audit_logs');
    Schema::create('authz_permission_audit_logs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('event_type');
        $table->string('severity')->default('info');
        $table->nullableMorphs('actor');
        $table->nullableMorphs('target');
        $table->json('old_value')->nullable();
        $table->json('new_value')->nullable();
        $table->json('context')->nullable();
        $table->timestamp('occurred_at');
        $table->timestamps();
    });

    // Ensure permissions table exists
    if (! Schema::hasTable('permissions')) {
        Schema::create('permissions', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('model_has_permissions')) {
        Schema::create('model_has_permissions', function ($table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->primary(['permission_id', 'model_type', 'model_id']);
        });
    }

    if (! Schema::hasTable('roles')) {
        Schema::create('roles', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('model_has_roles')) {
        Schema::create('model_has_roles', function ($table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->primary(['role_id', 'model_type', 'model_id']);
        });
    }

    if (! Schema::hasTable('role_has_permissions')) {
        Schema::create('role_has_permissions', function ($table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    // Clear permission cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->auditLogger = Mockery::mock(AuditLogger::class);
    $this->auditLogger->shouldReceive('log')->andReturn(null);

    $this->service = new DelegationService($this->auditLogger);
});

afterEach(function (): void {
    Schema::dropIfExists('authz_delegations');
    Schema::dropIfExists('authz_permission_audit_logs');
    Mockery::close();
});

test('can be instantiated', function (): void {
    expect($this->service)->toBeInstanceOf(DelegationService::class);
});

test('canDelegate returns false if user does not have permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::findOrCreate('orders.view', 'web');

    // User doesn't have the permission
    expect($this->service->canDelegate($user, 'orders.view'))->toBeFalse();
});

test('canDelegate returns false if user lacks delegation rights', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'candelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    // User has permission but no delegation rights
    $permission = Permission::findOrCreate('orders.view', 'web');
    $user->givePermissionTo($permission);

    expect($this->service->canDelegate($user, 'orders.view'))->toBeFalse();
});

test('canDelegate returns true with specific delegation permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'specificdelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    $permission = Permission::findOrCreate('orders.view', 'web');
    $delegatePermission = Permission::findOrCreate('delegate.orders.view', 'web');
    $user->givePermissionTo($permission);
    $user->givePermissionTo($delegatePermission);

    expect($this->service->canDelegate($user, 'orders.view'))->toBeTrue();
});

test('canDelegate returns true with wildcard delegation permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'wildcarddelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    $permission = Permission::findOrCreate('orders.view', 'web');
    $wildcardPermission = Permission::findOrCreate('delegate.*', 'web');
    $user->givePermissionTo($permission);
    $user->givePermissionTo($wildcardPermission);

    expect($this->service->canDelegate($user, 'orders.view'))->toBeTrue();
});

test('canDelegate returns false for object without can method', function (): void {
    $user = new stdClass;
    $user->id = 1;

    expect($this->service->canDelegate($user, 'orders.view'))->toBeFalse();
});

test('delegate creates delegation record', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'delegator@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'delegatee@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('delegate.orders.view', 'web');
    $delegator->givePermissionTo('orders.view');
    $delegator->givePermissionTo('delegate.orders.view');

    $delegation = $this->service->delegate($delegator, $delegatee, 'orders.view');

    expect($delegation)->toBeInstanceOf(Delegation::class)
        ->and($delegation->delegator_id)->toBe($delegator->id)
        ->and($delegation->delegatee_id)->toBe($delegatee->id)
        ->and($delegation->permission)->toBe('orders.view');
});

test('delegate throws exception if user cannot delegate', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'cannotdelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'cannotdelegatee@example.com',
        'password' => bcrypt('password'),
    ]);

    expect(fn () => $this->service->delegate($delegator, $delegatee, 'orders.view'))
        ->toThrow(CannotDelegateException::class, 'Cannot delegate orders.view');
});

test('delegate grants permission to delegatee', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'grantperm@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'receiveperm@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('delegate.orders.view', 'web');
    $delegator->givePermissionTo('orders.view');
    $delegator->givePermissionTo('delegate.orders.view');

    $this->service->delegate($delegator, $delegatee, 'orders.view');

    $delegatee->refresh();
    expect($delegatee->hasPermissionTo('orders.view'))->toBeTrue();
});

test('delegate with redelegate grants delegation permission', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'redelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'canredelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('delegate.orders.view', 'web');
    $delegator->givePermissionTo('orders.view');
    $delegator->givePermissionTo('delegate.orders.view');

    $this->service->delegate($delegator, $delegatee, 'orders.view', null, true);

    $delegatee->refresh();
    expect($delegatee->hasPermissionTo('orders.view'))->toBeTrue()
        ->and($delegatee->hasPermissionTo('delegate.orders.view'))->toBeTrue();
});

test('delegate with expiry sets expires_at', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'expirydelegate@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'expirydelegatee@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('delegate.orders.view', 'web');
    $delegator->givePermissionTo('orders.view');
    $delegator->givePermissionTo('delegate.orders.view');

    $expiresAt = now()->addDays(7);
    $delegation = $this->service->delegate($delegator, $delegatee, 'orders.view', $expiresAt);

    expect($delegation->expires_at->format('Y-m-d'))->toBe($expiresAt->format('Y-m-d'));
});

test('revoke marks delegation as revoked', function (): void {
    $delegator = User::create([
        'name' => 'Delegator',
        'email' => 'revokedelegator@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = User::create([
        'name' => 'Delegatee',
        'email' => 'revokedelegatee@example.com',
        'password' => bcrypt('password'),
    ]);

    // Give delegatee the permission so revoke can remove it
    Permission::findOrCreate('orders.view', 'web');
    $delegatee->givePermissionTo('orders.view');

    $delegation = Delegation::create([
        'delegator_id' => $delegator->id,
        'delegatee_id' => $delegatee->id,
        'permission' => 'orders.view',
        'can_redelegate' => false,
    ]);

    $this->service->revoke($delegation);

    $delegation->refresh();
    $delegatee->refresh();

    expect($delegation->revoked_at)->not->toBeNull()
        ->and($delegatee->hasPermissionTo('orders.view'))->toBeFalse();
});

test('revoke cascades to sub-delegations', function (): void {
    $userA = User::create([
        'name' => 'User A',
        'email' => 'usera@example.com',
        'password' => bcrypt('password'),
    ]);

    $userB = User::create([
        'name' => 'User B',
        'email' => 'userb@example.com',
        'password' => bcrypt('password'),
    ]);

    $userC = User::create([
        'name' => 'User C',
        'email' => 'userc@example.com',
        'password' => bcrypt('password'),
    ]);

    // Setup permissions
    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('delegate.orders.view', 'web');
    $userB->givePermissionTo('orders.view');
    $userB->givePermissionTo('delegate.orders.view');
    $userC->givePermissionTo('orders.view');

    // Create parent delegation: A -> B
    $parent = Delegation::create([
        'delegator_id' => $userA->id,
        'delegatee_id' => $userB->id,
        'permission' => 'orders.view',
        'can_redelegate' => true,
    ]);

    // Create child delegation: B -> C
    $child = Delegation::create([
        'delegator_id' => $userB->id,
        'delegatee_id' => $userC->id,
        'permission' => 'orders.view',
        'can_redelegate' => false,
    ]);

    $this->service->revoke($parent);

    $parent->refresh();
    $child->refresh();

    expect($parent->revoked_at)->not->toBeNull()
        ->and($child->revoked_at)->not->toBeNull();
});

test('getDelegationsFor returns active delegations for user', function (): void {
    $userId = 'user-123';

    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
    ]);

    Delegation::create([
        'delegator_id' => 'delegator-2',
        'delegatee_id' => $userId,
        'permission' => 'orders.create',
    ]);

    // Revoked delegation - should not appear
    Delegation::create([
        'delegator_id' => 'delegator-3',
        'delegatee_id' => $userId,
        'permission' => 'orders.delete',
        'revoked_at' => now(),
    ]);

    $user = new stdClass;
    $user->id = $userId;

    $delegations = $this->service->getDelegationsFor($user);

    expect($delegations)->toHaveCount(2);
});

test('getDelegationsFor excludes expired delegations', function (): void {
    $userId = 'user-expired';

    // Active delegation
    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
    ]);

    // Expired delegation
    Delegation::create([
        'delegator_id' => 'delegator-2',
        'delegatee_id' => $userId,
        'permission' => 'orders.create',
        'expires_at' => now()->subDay(),
    ]);

    $user = new stdClass;
    $user->id = $userId;

    $delegations = $this->service->getDelegationsFor($user);

    expect($delegations)->toHaveCount(1)
        ->and($delegations->first()->permission)->toBe('orders.view');
});

test('getDelegationsBy returns delegations made by user', function (): void {
    $userId = 'delegator-user';

    Delegation::create([
        'delegator_id' => $userId,
        'delegatee_id' => 'delegatee-1',
        'permission' => 'orders.view',
    ]);

    Delegation::create([
        'delegator_id' => $userId,
        'delegatee_id' => 'delegatee-2',
        'permission' => 'orders.create',
    ]);

    // Revoked - should not appear
    Delegation::create([
        'delegator_id' => $userId,
        'delegatee_id' => 'delegatee-3',
        'permission' => 'orders.delete',
        'revoked_at' => now(),
    ]);

    $user = new stdClass;
    $user->id = $userId;

    $delegations = $this->service->getDelegationsBy($user);

    expect($delegations)->toHaveCount(2);
});

test('hasDelegatedPermission returns true for active delegation', function (): void {
    $userId = 'has-delegation';

    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
    ]);

    $user = new stdClass;
    $user->id = $userId;

    expect($this->service->hasDelegatedPermission($user, 'orders.view'))->toBeTrue();
});

test('hasDelegatedPermission returns false for revoked delegation', function (): void {
    $userId = 'revoked-delegation';

    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
        'revoked_at' => now(),
    ]);

    $user = new stdClass;
    $user->id = $userId;

    expect($this->service->hasDelegatedPermission($user, 'orders.view'))->toBeFalse();
});

test('hasDelegatedPermission returns false for expired delegation', function (): void {
    $userId = 'expired-delegation';

    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
        'expires_at' => now()->subDay(),
    ]);

    $user = new stdClass;
    $user->id = $userId;

    expect($this->service->hasDelegatedPermission($user, 'orders.view'))->toBeFalse();
});

test('hasDelegatedPermission returns false for different permission', function (): void {
    $userId = 'diff-permission';

    Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => $userId,
        'permission' => 'orders.view',
    ]);

    $user = new stdClass;
    $user->id = $userId;

    expect($this->service->hasDelegatedPermission($user, 'orders.create'))->toBeFalse();
});

test('cleanupExpired revokes expired delegations', function (): void {
    $delegator1 = User::create([
        'name' => 'Delegator 1',
        'email' => 'cleanupd1@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee1 = User::create([
        'name' => 'Delegatee 1',
        'email' => 'cleanupe1@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegator2 = User::create([
        'name' => 'Delegator 2',
        'email' => 'cleanupd2@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee2 = User::create([
        'name' => 'Delegatee 2',
        'email' => 'cleanupe2@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegator3 = User::create([
        'name' => 'Delegator 3',
        'email' => 'cleanupd3@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee3 = User::create([
        'name' => 'Delegatee 3',
        'email' => 'cleanupe3@example.com',
        'password' => bcrypt('password'),
    ]);

    // Create permissions
    Permission::findOrCreate('orders.view', 'web');
    Permission::findOrCreate('orders.create', 'web');
    Permission::findOrCreate('orders.delete', 'web');

    // Give delegatees permissions
    $delegatee2->givePermissionTo('orders.create');
    $delegatee3->givePermissionTo('orders.delete');

    // Active delegation (no expiry)
    Delegation::create([
        'delegator_id' => $delegator1->id,
        'delegatee_id' => $delegatee1->id,
        'permission' => 'orders.view',
    ]);

    // Expired delegation 1
    Delegation::create([
        'delegator_id' => $delegator2->id,
        'delegatee_id' => $delegatee2->id,
        'permission' => 'orders.create',
        'expires_at' => now()->subDay(),
    ]);

    // Expired delegation 2
    Delegation::create([
        'delegator_id' => $delegator3->id,
        'delegatee_id' => $delegatee3->id,
        'permission' => 'orders.delete',
        'expires_at' => now()->subHour(),
    ]);

    $count = $this->service->cleanupExpired();

    expect($count)->toBe(2);
});

test('getDelegationChain returns single delegation for no chain', function (): void {
    $delegation = Delegation::create([
        'delegator_id' => 'delegator-1',
        'delegatee_id' => 'delegatee-1',
        'permission' => 'orders.view',
    ]);

    $chain = $this->service->getDelegationChain($delegation);

    expect($chain)->toHaveCount(1)
        ->and($chain->first()->id)->toBe($delegation->id);
});

test('getDelegationChain includes parent delegations', function (): void {
    // Parent delegation: A -> B
    $parent = Delegation::create([
        'delegator_id' => 'user-a',
        'delegatee_id' => 'user-b',
        'permission' => 'orders.view',
        'can_redelegate' => true,
    ]);

    // Child delegation: B -> C
    $child = Delegation::create([
        'delegator_id' => 'user-b',
        'delegatee_id' => 'user-c',
        'permission' => 'orders.view',
    ]);

    $chain = $this->service->getDelegationChain($child);

    expect($chain)->toHaveCount(2)
        ->and($chain->first()->delegator_id)->toBe('user-a')
        ->and($chain->last()->delegator_id)->toBe('user-b');
});

test('getDelegationChain includes child delegations', function (): void {
    // Parent delegation: A -> B
    $parent = Delegation::create([
        'delegator_id' => 'user-a',
        'delegatee_id' => 'user-b',
        'permission' => 'orders.view',
        'can_redelegate' => true,
    ]);

    // Child delegation: B -> C
    $child = Delegation::create([
        'delegator_id' => 'user-b',
        'delegatee_id' => 'user-c',
        'permission' => 'orders.view',
    ]);

    $chain = $this->service->getDelegationChain($parent);

    expect($chain)->toHaveCount(2)
        ->and($chain->first()->delegator_id)->toBe('user-a')
        ->and($chain->last()->delegator_id)->toBe('user-b');
});
