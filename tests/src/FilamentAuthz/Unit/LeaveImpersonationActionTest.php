<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;

describe('LeaveImpersonationAction', function (): void {
    it('falls back to root path for external back-to urls', function (): void {
        $method = new ReflectionMethod(LeaveImpersonationAction::class, 'sanitizeBackToUrl');

        expect($method->invoke(null, 'https://evil.example/owned'))->toBe('/')
            ->and($method->invoke(null, '//evil.example/owned'))->toBe('/');
    });

    it('keeps safe relative back-to urls', function (): void {
        $method = new ReflectionMethod(LeaveImpersonationAction::class, 'sanitizeBackToUrl');

        expect($method->invoke(null, '/admin'))->toBe('/admin')
            ->and($method->invoke(null, '/'))->toBe('/');
    });
});
