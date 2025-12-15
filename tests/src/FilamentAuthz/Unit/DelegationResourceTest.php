<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filament-authz.enterprise.delegation.enabled', true);
});

describe('DelegationResource', function (): void {
    it('returns correct model', function (): void {
        expect(DelegationResource::getModel())->toBe(Delegation::class);
    });

    it('returns navigation icon', function (): void {
        expect(DelegationResource::getNavigationIcon())->toBe('heroicon-o-arrows-right-left');
    });

    it('returns navigation label', function (): void {
        expect(DelegationResource::getNavigationLabel())->toBe('Delegations');
    });

    it('returns navigation group', function (): void {
        expect(DelegationResource::getNavigationGroup())->toBe('Authorization');
    });

    it('returns navigation sort', function (): void {
        expect(DelegationResource::getNavigationSort())->toBe(45);
    });

    it('returns navigation badge color', function (): void {
        expect(DelegationResource::getNavigationBadgeColor())->toBe('info');
    });

    it('allows access when delegation feature is enabled', function (): void {
        config()->set('filament-authz.enterprise.delegation.enabled', true);

        expect(DelegationResource::canAccess())->toBeTrue();
    });

    it('denies access when delegation feature is disabled', function (): void {
        config()->set('filament-authz.enterprise.delegation.enabled', false);

        expect(DelegationResource::canAccess())->toBeFalse();
    });

    it('returns correct pages including view', function (): void {
        $pages = DelegationResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('returns empty relations array', function (): void {
        expect(DelegationResource::getRelations())->toBe([]);
    });

    it('returns navigation badge when active delegations exist', function (): void {
        // Create active delegation
        Delegation::create([
            'delegator_type' => 'App\Models\User',
            'delegator_id' => '1',
            'delegatee_type' => 'App\Models\User',
            'delegatee_id' => '2',
            'permission' => 'user.view',
            'expires_at' => null,
            'revoked_at' => null,
            'can_redelegate' => false,
        ]);

        expect(DelegationResource::getNavigationBadge())->toBe('1');
    });

    it('returns null badge when no active delegations', function (): void {
        // No delegations created

        expect(DelegationResource::getNavigationBadge())->toBeNull();
    });
});
