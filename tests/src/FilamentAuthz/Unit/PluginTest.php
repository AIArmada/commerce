<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use Filament\Contracts\Plugin;

describe('FilamentAuthzPlugin', function (): void {
    it('can be instantiated', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('has an id', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('implements Plugin interface', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(Plugin::class);
    });
});
