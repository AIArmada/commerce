<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

describe('Tab Configuration', function (): void {
    it('can enable resources tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->resourcesTab();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable resources tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->resourcesTab(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can enable pages tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->pagesTab();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable pages tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->pagesTab(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can enable widgets tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->widgetsTab();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable widgets tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->widgetsTab(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can enable custom permissions tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->customPermissionsTab();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable custom permissions tab', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->customPermissionsTab(false);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('Permission Format Settings', function (): void {
    it('can set permission separator', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionSeparator('_');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can set permission case format', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('camel');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports snake case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('snake');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports kebab case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('kebab');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports camel case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('camel');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports pascal case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('pascal');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports upper snake case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('upper_snake');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('supports lower case', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->permissionCase('lower');

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('Exclude Settings', function (): void {
    it('can exclude resources', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludeResources(['UserResource', 'SettingResource']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can exclude pages', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludePages(['Dashboard', 'Profile']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can exclude widgets', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludeWidgets(['StatsWidget', 'ChartWidget']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can use closure for excludeResources', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludeResources(fn () => ['UserResource']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can use closure for excludePages', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludePages(fn () => ['Dashboard']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can use closure for excludeWidgets', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->excludeWidgets(fn () => ['StatsWidget']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('Navigation Settings', function (): void {
    it('can register navigation', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->registerNavigation();

        expect($plugin->shouldRegisterNavigation())->toBeTrue();
    });

    it('can disable navigation registration', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->registerNavigation(false);

        expect($plugin->shouldRegisterNavigation())->toBeFalse();
    });
});
