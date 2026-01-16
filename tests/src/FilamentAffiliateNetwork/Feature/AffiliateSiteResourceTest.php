<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Support\Icons\Heroicon;

describe('AffiliateSiteResource', function (): void {
    test('has correct model', function (): void {
        expect(AffiliateSiteResource::getModel())->toBe(AffiliateSite::class);
    });

    test('returns pages array', function (): void {
        $pages = AffiliateSiteResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('has navigation group from config', function (): void {
        config(['filament-affiliate-network.navigation.group' => 'Affiliate Network']);

        expect(AffiliateSiteResource::getNavigationGroup())->toBe('Affiliate Network');
    });

    test('has navigation sort from config', function (): void {
        config(['filament-affiliate-network.navigation.sort' => 50]);

        expect(AffiliateSiteResource::getNavigationSort())->toBe(50);
    });

    test('has correct navigation icon', function (): void {
        expect(AffiliateSiteResource::getNavigationIcon())->toBe(Heroicon::OutlinedGlobeAlt);
    });

    test('has correct model label', function (): void {
        expect(AffiliateSiteResource::getModelLabel())->toBe('Site');
    });

    test('has correct plural model label', function (): void {
        expect(AffiliateSiteResource::getPluralModelLabel())->toBe('Sites');
    });

    test('has empty relation managers', function (): void {
        $relations = AffiliateSiteResource::getRelations();

        expect($relations)->toBeArray()->toBeEmpty();
    });
});
