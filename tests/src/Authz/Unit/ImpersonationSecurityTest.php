<?php

declare(strict_types=1);

use AIArmada\Authz\Guard\SessionGuard;
use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\BackToUrlSanitizer;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;

use function AIArmada\Authz\can_be_impersonated;

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

it('takes and leaves impersonation through the explicit escape hatch', function (): void {
    $actor = User::query()->create([
        'name' => 'Escape Hatch Actor',
        'email' => 'escape-hatch-actor@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Escape Hatch Target',
        'email' => 'escape-hatch-target@example.com',
        'password' => 'secret',
    ]);

    $this->be($actor);

    $manager = app(ImpersonateManager::class);

    expect($manager->take($actor, $target, 'web', null, false))->toBeTrue()
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
