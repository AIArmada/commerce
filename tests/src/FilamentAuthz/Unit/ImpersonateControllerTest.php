<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Http\Controllers\ImpersonateController;
use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;

class ImpersonationPlainUser extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function getTable(): string
    {
        return 'users';
    }
}

class ImpersonationCustomUser extends ImpersonationPlainUser
{
    public function canImpersonate(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    config()->set('filament-authz.scoped_to_tenant', false);
    config()->set('filament-authz.impersonate.guard', 'web');
});

describe('ImpersonateController', function (): void {
    it('resolves user model class from configured impersonation guard provider', function (): void {
        config()->set('auth.guards.custom.provider', 'custom_users');
        config()->set('auth.providers.custom_users.model', User::class);

        $method = new ReflectionMethod(ImpersonateController::class, 'resolveUserModelClass');

        /** @var class-string $resolved */
        $resolved = $method->invoke(null, 'custom');

        expect($resolved)->toBe(User::class);
    });

    it('falls back to root path for invalid redirect targets', function (): void {
        $method = new ReflectionMethod(ImpersonateController::class, 'sanitizeRedirectPath');

        expect($method->invoke(null, 'https://evil.example/owned'))->toBe('/')
            ->and($method->invoke(null, '//evil.example/owned'))->toBe('/')
            ->and($method->invoke(null, '/not-a-registered-panel'))->toBe('/');
    });

    it('uses the impersonation manager session contract for route-based impersonation', function (): void {
        $superAdminRole = \AIArmada\FilamentAuthz\Models\Role::findOrCreate((string) config('filament-authz.super_admin_role'), 'web');

        $impersonator = User::query()->create([
            'name' => 'Impersonator',
            'email' => 'impersonator@example.com',
            'password' => 'secret',
        ]);
        $impersonator->assignRole($superAdminRole);

        $target = User::query()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => 'secret',
        ]);

        $response = $this
            ->actingAs($impersonator)
            ->withHeader('referer', '/admin')
            ->post(route('filament-authz.impersonate', ['userId' => $target->getKey()]), [
                'redirect_to' => '/admin',
            ]);

        $response->assertRedirect('/admin');

        expect(session(ImpersonateManager::SESSION_KEY))->toBe($impersonator->getAuthIdentifier())
            ->and(session(ImpersonateManager::SESSION_GUARD))->toBe('web')
            ->and(session(ImpersonateManager::SESSION_GUARD_USING))->toBe('web')
            ->and(session(ImpersonateManager::SESSION_BACK_TO))->toBe('/admin')
            ->and(Auth::guard('web')->id())->toBe($target->getAuthIdentifier());
    });

    it('denies direct route impersonation for users without impersonation capabilities', function (): void {
        config()->set('auth.providers.users.model', ImpersonationPlainUser::class);

        $impersonator = ImpersonationPlainUser::query()->create([
            'name' => 'Plain Impersonator',
            'email' => 'plain-impersonator@example.com',
            'password' => 'secret',
        ]);

        $target = ImpersonationPlainUser::query()->create([
            'name' => 'Plain Target',
            'email' => 'plain-target@example.com',
            'password' => 'secret',
        ]);

        $response = $this
            ->actingAs($impersonator)
            ->post(route('filament-authz.impersonate', ['userId' => $target->getKey()]), [
                'redirect_to' => '/',
            ]);

        $response->assertForbidden();

        expect(session()->has(ImpersonateManager::SESSION_KEY))->toBeFalse()
            ->and(Auth::guard('web')->id())->toBe($impersonator->getAuthIdentifier());
    });

    it('allows direct route impersonation when custom canImpersonate returns true', function (): void {
        config()->set('auth.providers.users.model', ImpersonationCustomUser::class);

        $impersonator = ImpersonationCustomUser::query()->create([
            'name' => 'Custom Impersonator',
            'email' => 'custom-impersonator@example.com',
            'password' => 'secret',
        ]);

        $target = ImpersonationCustomUser::query()->create([
            'name' => 'Custom Target',
            'email' => 'custom-target@example.com',
            'password' => 'secret',
        ]);

        $response = $this
            ->actingAs($impersonator)
            ->withHeader('referer', '/admin')
            ->post(route('filament-authz.impersonate', ['userId' => $target->getKey()]), [
                'redirect_to' => '/admin',
            ]);

        $response->assertRedirect('/admin');

        expect(session(ImpersonateManager::SESSION_KEY))->toBe($impersonator->getAuthIdentifier())
            ->and(Auth::guard('web')->id())->toBe($target->getAuthIdentifier());
    });

    it('only allows leaving impersonation via post', function (): void {
        $response = $this->get(route('filament-authz.impersonate.leave'));

        $response->assertStatus(405);
    });

    it('restores the impersonator when leaving through the route', function (): void {
        $superAdminRole = \AIArmada\FilamentAuthz\Models\Role::findOrCreate((string) config('filament-authz.super_admin_role'), 'web');

        $impersonator = User::query()->create([
            'name' => 'Leave Impersonator',
            'email' => 'leave-impersonator@example.com',
            'password' => 'secret',
        ]);
        $impersonator->assignRole($superAdminRole);

        $target = User::query()->create([
            'name' => 'Leave Target',
            'email' => 'leave-target@example.com',
            'password' => 'secret',
        ]);

        $this
            ->actingAs($impersonator)
            ->withHeader('referer', '/admin')
            ->post(route('filament-authz.impersonate', ['userId' => $target->getKey()]), [
                'redirect_to' => '/admin',
            ])
            ->assertRedirect('/admin');

        $leaveResponse = $this->post(route('filament-authz.impersonate.leave'));

        $leaveResponse->assertRedirect('/admin');

        expect(session()->has(ImpersonateManager::SESSION_KEY))->toBeFalse()
            ->and(session()->has(ImpersonateManager::SESSION_GUARD))->toBeFalse()
            ->and(session()->has(ImpersonateManager::SESSION_GUARD_USING))->toBeFalse()
            ->and(Auth::guard('web')->id())->toBe($impersonator->getAuthIdentifier());
    });

    it('sanitizes poisoned leave redirect targets to root path', function (): void {
        $impersonator = User::query()->create([
            'name' => 'Poisoned Leave Impersonator',
            'email' => 'poisoned-leave-impersonator@example.com',
            'password' => 'secret',
        ]);

        $target = User::query()->create([
            'name' => 'Poisoned Leave Target',
            'email' => 'poisoned-leave-target@example.com',
            'password' => 'secret',
        ]);

        $this->actingAs($target);

        session()->put(ImpersonateManager::SESSION_KEY, $impersonator->getAuthIdentifier());
        session()->put(ImpersonateManager::SESSION_GUARD, 'web');
        session()->put(ImpersonateManager::SESSION_GUARD_USING, 'web');
        session()->put(ImpersonateManager::SESSION_BACK_TO, 'https://evil.example/owned');

        $response = $this->post(route('filament-authz.impersonate.leave'));

        $response->assertRedirect('/');
    });

    it('stores password hash under impersonated and restored guard keys', function (): void {
        config()->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        config()->set('auth.defaults.guard', 'admin');

        $impersonator = User::query()->create([
            'name' => 'Guard A Impersonator',
            'email' => 'guard-a-impersonator@example.com',
            'password' => 'secret',
        ]);

        $target = User::query()->create([
            'name' => 'Guard B Target',
            'email' => 'guard-b-target@example.com',
            'password' => 'secret',
        ]);

        $this->actingAs($impersonator, 'web');

        $manager = app(ImpersonateManager::class);

        expect($manager->take($impersonator, $target, 'admin', '/admin'))->toBeTrue()
            ->and(session()->has('password_hash_admin'))->toBeTrue();

        session()->forget('password_hash_web');

        expect($manager->leave())->toBeTrue()
            ->and(session()->has('password_hash_web'))->toBeTrue();
    });
});
