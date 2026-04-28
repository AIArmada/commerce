<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Http\Controllers\ImpersonateController;

describe('ImpersonateController', function (): void {
    it('resolves user model class from configured impersonation guard provider', function (): void {
        config()->set('auth.guards.custom.provider', 'custom_users');
        config()->set('auth.providers.custom_users.model', User::class);

        $method = new ReflectionMethod(ImpersonateController::class, 'resolveUserModelClass');
        $method->setAccessible(true);

        /** @var class-string $resolved */
        $resolved = $method->invoke(null, 'custom');

        expect($resolved)->toBe(User::class);
    });

    it('falls back to root path for invalid redirect targets', function (): void {
        $method = new ReflectionMethod(ImpersonateController::class, 'sanitizeRedirectPath');
        $method->setAccessible(true);

        expect($method->invoke(null, 'https://evil.example/owned'))->toBe('/')
            ->and($method->invoke(null, '//evil.example/owned'))->toBe('/')
            ->and($method->invoke(null, '/not-a-registered-panel'))->toBe('/');
    });
});
