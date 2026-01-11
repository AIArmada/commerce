<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

describe('Multi-Panel Support', function () {
    it('can be scoped to tenant', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->scopedToTenant();

        expect($plugin->isScopedToTenant())->toBeTrue();
    });

    it('can disable tenant scoping', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->scopedToTenant(false);

        expect($plugin->isScopedToTenant())->toBeFalse();
    });

    it('can set tenant ownership relationship', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->tenantOwnershipRelationship('team');

        expect($plugin->getTenantOwnershipRelationship())->toBe('team');
    });

    it('can use closure for tenant scoping', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->scopedToTenant(fn () => true);

        expect($plugin->isScopedToTenant())->toBeTrue();
    });

    it('can use closure for tenant relationship', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->tenantOwnershipRelationship(fn () => 'organization');

        expect($plugin->getTenantOwnershipRelationship())->toBe('organization');
    });

    it('returns null panel when not registered', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getPanel())->toBeNull();
    });

    it('has fluent API for tenant methods', function () {
        $plugin = FilamentAuthzPlugin::make();

        $result = $plugin->scopedToTenant()
            ->tenantOwnershipRelationship('teams');

        expect($result)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('Plugin Configuration', function () {
    it('can enable role resource', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable role resource', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can set navigation group', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationGroup('Settings');

        expect($plugin->getNavigationGroup())->toBe('Settings');
    });

    it('can set navigation icon', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationIcon('heroicon-o-shield-check');

        expect($plugin->getNavigationIcon())->toBe('heroicon-o-shield-check');
    });

    it('can set navigation sort', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->navigationSort(10);

        expect($plugin->getNavigationSort())->toBe(10);
    });

    it('can set grid columns', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->gridColumns(3);

        expect($plugin->getGridColumns())->toBe(3);
    });

    it('can set checkbox columns', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->checkboxColumns(4);

        expect($plugin->getCheckboxColumns())->toBe(4);
    });

    it('has correct plugin id', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('can be instantiated via make method', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can use fluent interface chaining', function () {
        $plugin = FilamentAuthzPlugin::make()
            ->roleResource()
            ->permissionResource()
            ->navigationGroup('Access Control')
            ->navigationIcon('heroicon-o-lock-closed')
            ->navigationSort(5)
            ->gridColumns(3)
            ->checkboxColumns(4)
            ->resourcesTab()
            ->pagesTab()
            ->widgetsTab()
            ->customPermissionsTab()
            ->scopedToTenant()
            ->tenantOwnershipRelationship('team');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        expect($plugin->getNavigationGroup())->toBe('Access Control');
        expect($plugin->getNavigationIcon())->toBe('heroicon-o-lock-closed');
        expect($plugin->getNavigationSort())->toBe(5);
        expect($plugin->getGridColumns())->toBe(3);
        expect($plugin->getCheckboxColumns())->toBe(4);
        expect($plugin->isScopedToTenant())->toBeTrue();
        expect($plugin->getTenantOwnershipRelationship())->toBe('team');
    });
});
