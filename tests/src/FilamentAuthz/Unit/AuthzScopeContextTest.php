<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Support\AuthzScopeContext;

describe('AuthzScopeContext', function (): void {
    beforeEach(function (): void {
        AuthzScopeContext::clear();
    });

    it('stores and resolves scope overrides', function (): void {
        AuthzScopeContext::set('scope-a');

        expect(AuthzScopeContext::resolve())->toBe('scope-a');
    });

    it('clears scope overrides', function (): void {
        AuthzScopeContext::set('scope-a');
        AuthzScopeContext::clear();

        expect(AuthzScopeContext::resolve())->toBeNull();
    });

    it('tracks whether an override exists', function (): void {
        expect(AuthzScopeContext::hasOverride())->toBeFalse();

        AuthzScopeContext::set('scope-a');

        expect(AuthzScopeContext::hasOverride())->toBeTrue();

        AuthzScopeContext::clear();

        expect(AuthzScopeContext::hasOverride())->toBeFalse();
    });

    it('restores previous scope after withScope', function (): void {
        AuthzScopeContext::set('scope-before');

        $resolvedInside = AuthzScopeContext::withScope('scope-inside', function (): string | int | null {
            return AuthzScopeContext::resolve();
        });

        expect($resolvedInside)->toBe('scope-inside')
            ->and(AuthzScopeContext::resolve())->toBe('scope-before');
    });
});
