<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Concerns\HasOwnerPermissions;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use ReflectionMethod;

// Create a concrete test model using the trait
class TestModelWithOwnerPermissions extends Model
{
    use HasOwnerPermissions;

    protected $table = 'test_items';

    protected $fillable = ['user_id', 'name'];

    protected $attributes = [
        'user_id' => null,
    ];
}

afterEach(function (): void {
    Mockery::close();
});

describe('HasOwnerPermissions::canUserPerform', function (): void {
    it('returns true when user has standard permission', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 1;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(2); // Different user

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canForResource')
            ->with($user, 'test_items.view', $model)
            ->andReturn(true);
        app()->instance(ContextualAuthorizationService::class, $service);

        $result = $model->canUserPerform($user, 'view');

        expect($result)->toBeTrue();
    });

    it('returns true when owner has owner-specific permission', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 1;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(1); // Same user - owner

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canForResource')
            ->with($user, 'test_items.update', $model)
            ->andReturn(false); // No standard permission
        $service->shouldReceive('canWithContext')
            ->with($user, 'test_items.update.own', [
                'scope' => PermissionScope::Owner->value,
                'owner_id' => 1,
            ])
            ->andReturn(true);
        app()->instance(ContextualAuthorizationService::class, $service);

        $result = $model->canUserPerform($user, 'update');

        expect($result)->toBeTrue();
    });

    it('returns false when user has no permission', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 1;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(2); // Different user

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canForResource')
            ->with($user, 'test_items.delete', $model)
            ->andReturn(false);
        app()->instance(ContextualAuthorizationService::class, $service);

        $result = $model->canUserPerform($user, 'delete');

        expect($result)->toBeFalse();
    });

    it('returns false when owner lacks owner-specific permission', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 1;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(1); // Owner

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canForResource')
            ->andReturn(false);
        $service->shouldReceive('canWithContext')
            ->andReturn(false);
        app()->instance(ContextualAuthorizationService::class, $service);

        $result = $model->canUserPerform($user, 'forceDelete');

        expect($result)->toBeFalse();
    });
});

describe('HasOwnerPermissions::isOwnedBy', function (): void {
    it('returns true when user owns the model', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 42;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(42);

        expect($model->isOwnedBy($user))->toBeTrue();
    });

    it('returns false when user does not own the model', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = 42;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(99);

        expect($model->isOwnedBy($user))->toBeFalse();
    });

    it('returns false when owner key is null', function (): void {
        $model = new TestModelWithOwnerPermissions;
        $model->user_id = null;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(1);

        expect($model->isOwnedBy($user))->toBeFalse();
    });
});

describe('HasOwnerPermissions::scopeOwnedBy', function (): void {
    it('applies where clause for owner', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(5);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->with('user_id', 5)
            ->once()
            ->andReturnSelf();

        $result = $model->scopeOwnedBy($query, $user);

        expect($result)->toBe($query);
    });
});

describe('HasOwnerPermissions::scopeViewableBy', function (): void {
    it('returns all records when user has global viewAny permission', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(1);

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canWithContext')
            ->with($user, 'test_items.viewAny', [])
            ->andReturn(true);
        app()->instance(ContextualAuthorizationService::class, $service);

        $query = Mockery::mock(Builder::class);
        // Should not apply where clause

        $result = $model->scopeViewableBy($query, $user);

        expect($result)->toBe($query);
    });

    it('scopes to owned records when user lacks global viewAny', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $user = Mockery::mock();
        $user->shouldReceive('getKey')->andReturn(3);

        $service = Mockery::mock(ContextualAuthorizationService::class);
        $service->shouldReceive('canWithContext')
            ->with($user, 'test_items.viewAny', [])
            ->andReturn(false);
        app()->instance(ContextualAuthorizationService::class, $service);

        $query = Mockery::mock(Builder::class);
        // The trait calls $query->ownedBy($user) which is a scope
        $query->shouldReceive('ownedBy')
            ->with($user)
            ->once()
            ->andReturnSelf();

        $result = $model->scopeViewableBy($query, $user);

        expect($result)->toBe($query);
    });
});

describe('HasOwnerPermissions::getPermissionName', function (): void {
    it('generates permission name from table', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $reflection = new ReflectionMethod($model, 'getPermissionName');
        $reflection->setAccessible(true);

        $permission = $reflection->invoke($model, 'create');

        expect($permission)->toBe('test_items.create');
    });
});

describe('HasOwnerPermissions::getOwnerPermissionName', function (): void {
    it('generates owner permission name with .own suffix', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $reflection = new ReflectionMethod($model, 'getOwnerPermissionName');
        $reflection->setAccessible(true);

        $permission = $reflection->invoke($model, 'update');

        expect($permission)->toBe('test_items.update.own');
    });
});

describe('HasOwnerPermissions::getResourceName', function (): void {
    it('returns table name as resource name', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $reflection = new ReflectionMethod($model, 'getResourceName');
        $reflection->setAccessible(true);

        $name = $reflection->invoke($model);

        expect($name)->toBe('test_items');
    });

    it('strips authz_ prefix from table name', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'authz_roles';
        };

        $reflection = new ReflectionMethod($model, 'getResourceName');
        $reflection->setAccessible(true);

        $name = $reflection->invoke($model);

        expect($name)->toBe('roles');
    });

    it('strips inv_ prefix from table name', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'inv_stock_items';
        };

        $reflection = new ReflectionMethod($model, 'getResourceName');
        $reflection->setAccessible(true);

        $name = $reflection->invoke($model);

        expect($name)->toBe('stock_items');
    });

    it('strips vou_ prefix from table name', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'vou_vouchers';
        };

        $reflection = new ReflectionMethod($model, 'getResourceName');
        $reflection->setAccessible(true);

        $name = $reflection->invoke($model);

        expect($name)->toBe('vouchers');
    });
});

describe('HasOwnerPermissions::getOwnerKeyName', function (): void {
    it('returns user_id by default', function (): void {
        $model = new TestModelWithOwnerPermissions;

        $reflection = new ReflectionMethod($model, 'getOwnerKeyName');
        $reflection->setAccessible(true);

        $key = $reflection->invoke($model);

        expect($key)->toBe('user_id');
    });
});
