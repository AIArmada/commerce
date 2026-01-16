<?php

declare(strict_types=1);

use AIArmada\FilamentPromotions\FilamentPromotionsPlugin;

describe('FilamentPromotionsPlugin', function (): void {
    describe('make', function (): void {
        it('creates plugin instance', function (): void {
            $plugin = FilamentPromotionsPlugin::make();

            expect($plugin)->toBeInstanceOf(FilamentPromotionsPlugin::class);
        });
    });

    describe('getId', function (): void {
        it('returns correct id', function (): void {
            $plugin = FilamentPromotionsPlugin::make();

            expect($plugin->getId())->toBe('filament-promotions');
        });
    });
});
