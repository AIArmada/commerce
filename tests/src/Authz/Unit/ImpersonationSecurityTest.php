<?php

declare(strict_types=1);

use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\Authz\Features\Impersonation\Actions\ImpersonateManager;
use AIArmada\Authz\Features\Impersonation\EntryPoints\Auth\SessionGuard;
use AIArmada\Authz\Features\Impersonation\Support\BackToUrlSanitizer;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;

use function AIArmada\Authz\can_be_impersonated;

class ImpersonationCrossGuardUser extends User {}

it('refuses impersonation for actors without authorization', function (): void {
    $actor = User::query()->create([
        'name' => 'Unauthorized Actor',
        'email' => 'unauthorized-actor@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Impersonation Target',
        'email' => 'impersonation-target@example.com',
        'password' => 'secret',
    ]);

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target))->toBeFalse()
        ->and($manager->isImpersonating())->toBeFalse();
});

it('refuses self impersonation across int and string identifiers', function (): void {
    $manager = app(ImpersonateManager::class);

    $from = new GenericUser(['id' => 5]);
    $to = new GenericUser(['id' => '5']);

    expect($manager->take($from, $to))->toBeFalse()
        ->and($manager->isImpersonating())->toBeFalse();
});

it('refuses impersonation when the target opts out', function (): void {
    $actor = new class(['id' => 'actor-id']) extends GenericUser
    {
        public function canImpersonate(): bool
        {
            return true;
        }
    };
    $target = new class(['id' => 'target-id']) extends GenericUser
    {
        public function canBeImpersonated(): bool
        {
            return false;
        }
    };

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target))->toBeFalse()
        ->and($manager->isImpersonating())->toBeFalse();
});

it('takes and leaves impersonation as an authorized current user', function (): void {
    config()->set('authz.scopes.enforce', false);

    $actor = User::query()->create([
        'name' => 'Authorized Actor',
        'email' => 'authorized-actor@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Authorized Actor Target',
        'email' => 'authorized-actor-target@example.com',
        'password' => 'secret',
    ]);

    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));
    $this->be($actor);

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target, 'web'))->toBeTrue()
        ->and($manager->isImpersonating())->toBeTrue()
        ->and(auth()->guard('web')->user()?->is($target))->toBeTrue();

    expect($manager->leave())->toBeTrue()
        ->and($manager->isImpersonating())->toBeFalse()
        ->and(auth()->guard('web')->user()?->is($actor))->toBeTrue();
});

it('rejects backslash and control-character back-to urls', function (): void {
    expect(BackToUrlSanitizer::sanitize('/admin'))->toBe('/admin')
        ->and(BackToUrlSanitizer::sanitize('/\\evil.com'))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize("/admin\x00x"))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize('https://evil.example/x'))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize(''))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize(null))->toBe('/');
});

it('performs quiet login without firing the authenticated event', function (): void {
    $user = User::query()->create([
        'name' => 'Quiet Login User',
        'email' => 'quiet-login-user@example.com',
        'password' => 'secret',
    ]);

    Event::fake([Authenticated::class]);

    $guard = new SessionGuard('web', app('auth')->createUserProvider('users'), app('session.store'));
    $guard->setDispatcher(app('events'));
    $guard->quietLogin($user);

    Event::assertNotDispatched(Authenticated::class);
    expect($guard->user()?->is($user))->toBeTrue();
});

it('refuses self impersonation in the helper across int and string identifiers', function (): void {
    auth()->guard('web')->setUser(new GenericUser(['id' => 7]));

    expect(can_be_impersonated(new GenericUser(['id' => '7'])))->toBeFalse();
});

it('rejects non-http and credential-bearing absolute return URLs', function (): void {
    $host = request()->getHost();

    expect(BackToUrlSanitizer::sanitize('ftp://' . $host . '/admin'))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize('https://user:secret@' . $host . '/admin'))->toBe('/')
        ->and(BackToUrlSanitizer::sanitize('//' . $host . '/admin'))->toBe('/');
});

it('refuses impersonation when the supplied privileged actor is not authenticated', function (): void {
    $actor = User::factory()->create();
    $current = User::factory()->create();
    $target = User::factory()->create();

    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));
    $this->be($current);

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target, 'web'))->toBeFalse()
        ->and($manager->isImpersonating())->toBeFalse()
        ->and(auth()->guard('web')->user()?->is($current))->toBeTrue();
});

it('does not override the standard Laravel session driver', function (): void {
    config()->set('auth.guards.standard', ['driver' => 'session', 'provider' => 'users']);

    $guard = auth()->guard('standard');

    expect($guard)->toBeInstanceOf(Illuminate\Auth\SessionGuard::class)
        ->and($guard)->not->toBeInstanceOf(SessionGuard::class);
});

it('rejects a non-participating target guard without switching the actor', function (): void {
    config()->set('auth.guards.standard', ['driver' => 'session', 'provider' => 'users']);
    config()->set('authz.scopes.enforce', false);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));
    $this->be($actor);

    expect(app(ImpersonateManager::class)->take($actor, $target, 'standard'))->toBeFalse()
        ->and(auth()->guard('web')->user()?->is($actor))->toBeTrue()
        ->and(session()->has(ImpersonateManager::SESSION_IMPERSONATOR_ID))->toBeFalse();
});

it('preserves the restored password hash for same-guard impersonation', function (): void {
    config()->set('authz.scopes.enforce', false);
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));
    $this->be($actor);

    $manager = app(ImpersonateManager::class);
    expect($manager->take($actor, $target, 'web'))->toBeTrue()
        ->and($manager->leave())->toBeTrue()
        ->and(session()->has('password_hash_web'))->toBeTrue()
        ->and(auth()->guard('web')->user()?->is($actor))->toBeTrue();
});

it('logs out the impersonated account if the original account was removed', function (): void {
    config()->set('authz.scopes.enforce', false);
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));
    $this->be($actor);

    $manager = app(ImpersonateManager::class);
    expect($manager->take($actor, $target, 'web'))->toBeTrue();
    $actor->delete();

    expect($manager->leave())->toBeFalse()
        ->and($manager->isImpersonating())->toBeFalse()
        ->and(auth()->guard('web')->check())->toBeFalse();
});

it('rejects cross-port and downgraded back-to urls', function (): void {
    $host = request()->getHost();

    expect(BackToUrlSanitizer::sanitize('http://' . $host . ':8080/admin'))->toBe('/');

    request()->server->set('HTTPS', 'on');

    expect(BackToUrlSanitizer::sanitize('http://' . $host . '/admin'))->toBe('/');
});

it('allows a cross-guard take for distinct authenticatables sharing an identifier', function (): void {
    config()->set('auth.guards.admin', ['driver' => 'authz-session', 'provider' => 'admin_users']);
    config()->set('auth.providers.admin_users', ['driver' => 'eloquent', 'model' => ImpersonationCrossGuardUser::class]);
    config()->set('auth.defaults.guard', 'admin');
    config()->set('authz.scopes.enforce', false);

    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate((string) config('authz.super_admin_role'), 'web'));

    $target = ImpersonationCrossGuardUser::query()->findOrFail($actor->getKey());

    $this->actingAs($actor, 'web');
    session()->put('password_hash_web', 'legacy-hash');

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target, 'admin', '/admin'))->toBeTrue()
        ->and($manager->isImpersonating())->toBeTrue()
        ->and(session()->has('password_hash_web'))->toBeFalse()
        ->and(session()->has('password_hash_admin'))->toBeTrue()
        ->and(auth()->guard('admin')->user())->toBeInstanceOf(ImpersonationCrossGuardUser::class);
});
