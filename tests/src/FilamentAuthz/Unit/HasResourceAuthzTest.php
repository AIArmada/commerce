<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Concerns\HasResourceAuthz;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use ReflectionProperty;

// Create a concrete test class using the trait
class TestResourceWithAuthz
{
    use HasResourceAuthz;

    protected static string $model = TestModelForResource::class;

    public static function getModel(): string
    {
        return static::$model;
    }
}

class TestModelForResource extends Model
{
    public int $user_id = 1;

    protected $table = 'test_resources';
}

beforeEach(function (): void {
    // Reset static properties
    TestResourceWithAuthz::setPermissionPrefix('test_resource');
    TestResourceWithAuthz::setOwnerColumn('user_id');
    TestResourceWithAuthz::setOwnerAbilities(['view', 'update', 'delete']);
    TestResourceWithAuthz::restrictToOwned(false);
});

afterEach(function (): void {
    Mockery::close();
});

describe('HasResourceAuthz trait', function (): void {
    it('returns all standard abilities', function (): void {
        $abilities = TestResourceWithAuthz::getAllAbilities();

        expect($abilities)
            ->toContain('viewAny')
            ->toContain('view')
            ->toContain('create')
            ->toContain('update')
            ->toContain('delete')
            ->toContain('restore')
            ->toContain('forceDelete');
    });

    it('includes custom abilities when set', function (): void {
        TestResourceWithAuthz::abilities(['approve', 'ship', 'refund']);

        $abilities = TestResourceWithAuthz::getAllAbilities();

        expect($abilities)
            ->toContain('approve')
            ->toContain('ship')
            ->toContain('refund');
    });

    it('generates correct permission name', function (): void {
        TestResourceWithAuthz::setPermissionPrefix('orders');

        $permission = TestResourceWithAuthz::getPermissionFor('create');

        expect($permission)->toBe('orders.create');
    });

    it('uses model snake case for prefix when not set explicitly', function (): void {
        // Reset to use auto-detected prefix
        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'permissionPrefix');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        $permission = TestResourceWithAuthz::getPermissionFor('view');

        expect($permission)->toBe('test_model_for_resource.view');
    });

    it('returns false when user not authenticated', function (): void {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn(null);

        Filament::shouldReceive('auth')->andReturn($guard);

        $result = TestResourceWithAuthz::canPerform('view');

        expect($result)->toBeFalse();
    });

    it('allows super admin to perform any ability', function (): void {
        config(['filament-authz.super_admin_role' => 'super_admin']);

        // Create a mock that handles hasRole
        $user = new class
        {
            public function hasRole(string $role): bool
            {
                return $role === 'super_admin';
            }
        };

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $result = TestResourceWithAuthz::canPerform('forceDelete');

        expect($result)->toBeTrue();
    });

    it('checks owner permissions for record owner', function (): void {
        $user = Mockery::mock();
        $user->id = 1;
        $user->shouldReceive('hasRole')->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $record = new TestModelForResource();
        $record->user_id = 1;

        // Owner should be able to view their own record
        $result = TestResourceWithAuthz::canPerform('view', $record);

        expect($result)->toBeTrue();
    });

    it('denies owner abilities outside defined set', function (): void {
        $user = Mockery::mock();
        $user->id = 1;
        $user->shouldReceive('hasRole')->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')->andReturn(false);
        app()->instance(PermissionAggregator::class, $aggregator);

        $record = new TestModelForResource();
        $record->user_id = 1;

        // 'forceDelete' is not in owner abilities
        $result = TestResourceWithAuthz::canPerform('forceDelete', $record);

        expect($result)->toBeFalse();
    });

    it('checks aggregator permission for non-owners', function (): void {
        $user = Mockery::mock();
        $user->id = 2; // Different from record owner
        $user->shouldReceive('hasRole')->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test_resource.view')
            ->andReturn(true);
        app()->instance(PermissionAggregator::class, $aggregator);

        $record = new TestModelForResource();
        $record->user_id = 1;

        $result = TestResourceWithAuthz::canPerform('view', $record);

        expect($result)->toBeTrue();
    });

    it('can set permission prefix', function (): void {
        TestResourceWithAuthz::setPermissionPrefix('custom_prefix');

        $permission = TestResourceWithAuthz::getPermissionFor('create');

        expect($permission)->toBe('custom_prefix.create');
    });

    it('can restrict to owned records', function (): void {
        TestResourceWithAuthz::restrictToOwned(true);

        // Verify via reflection
        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'restrictToOwned');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toBeTrue();
    });

    it('can set owner column', function (): void {
        TestResourceWithAuthz::setOwnerColumn('created_by');

        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'ownerColumn');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toBe('created_by');
    });

    it('can set owner abilities', function (): void {
        TestResourceWithAuthz::setOwnerAbilities(['view', 'edit']);

        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'ownerAbilities');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toEqual(['view', 'edit']);
    });

    it('can scope resource to team', function (): void {
        TestResourceWithAuthz::scopeResourceToTeam('organization_id');

        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'resourceTeamScope');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toBe('organization_id');
    });
});

describe('scopeEloquentQueryWithPermissions', function (): void {
    it('applies team scope when configured', function (): void {
        TestResourceWithAuthz::scopeResourceToTeam('team_id');

        // Create a minimal model instance for tenant
        $tenant = new TestModelForResource();
        // Use reflection to set id since we can't assign to model directly without table
        $reflection = new ReflectionProperty(Model::class, 'attributes');
        $reflection->setAccessible(true);
        $reflection->setValue($tenant, ['id' => 123]);

        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->with('team_id', 123)
            ->once()
            ->andReturnSelf();

        $result = TestResourceWithAuthz::scopeEloquentQueryWithPermissions($query);

        expect($result)->toBe($query);
    });

    it('applies owner restriction when configured', function (): void {
        TestResourceWithAuthz::restrictToOwned(true);

        // Reset team scope
        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'resourceTeamScope');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        Filament::shouldReceive('getTenant')->andReturn(null);

        $user = Mockery::mock();
        $user->id = 5;

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test_resource.viewAny')
            ->andReturn(false);
        app()->instance(PermissionAggregator::class, $aggregator);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->with('user_id', 5)
            ->once()
            ->andReturnSelf();

        $result = TestResourceWithAuthz::scopeEloquentQueryWithPermissions($query);

        expect($result)->toBe($query);
    });

    it('does not apply owner restriction for users with viewAny permission', function (): void {
        TestResourceWithAuthz::restrictToOwned(true);

        // Reset team scope
        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'resourceTeamScope');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        Filament::shouldReceive('getTenant')->andReturn(null);

        $user = Mockery::mock();
        $user->id = 5;

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test_resource.viewAny')
            ->andReturn(true); // User has viewAny permission
        app()->instance(PermissionAggregator::class, $aggregator);

        $query = Mockery::mock(Builder::class);
        // Should NOT call where for owner restriction

        $result = TestResourceWithAuthz::scopeEloquentQueryWithPermissions($query);

        expect($result)->toBe($query);
    });

    it('returns query unchanged when no scope configured', function (): void {
        TestResourceWithAuthz::restrictToOwned(false);

        // Reset team scope
        $reflection = new ReflectionProperty(TestResourceWithAuthz::class, 'resourceTeamScope');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        Filament::shouldReceive('getTenant')->andReturn(null);

        $query = Mockery::mock(Builder::class);

        $result = TestResourceWithAuthz::scopeEloquentQueryWithPermissions($query);

        expect($result)->toBe($query);
    });
});
