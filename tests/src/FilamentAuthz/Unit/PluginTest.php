<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

describe('FilamentAuthzPlugin', function () {
    it('can be instantiated', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('has an id', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('implements Plugin interface', function () {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(\Filament\Contracts\Plugin::class);
    });
});
