<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use Filament\Support\Icons\Heroicon;

describe('AffiliateOfferCategoryResource', function (): void {
    test('has correct model', function (): void {
        expect(AffiliateOfferCategoryResource::getModel())->toBe(AffiliateOfferCategory::class);
    });

    test('returns pages array', function (): void {
        $pages = AffiliateOfferCategoryResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('has navigation group from config', function (): void {
        config(['filament-affiliate-network.navigation.group' => 'Affiliate Network']);

        expect(AffiliateOfferCategoryResource::getNavigationGroup())->toBe('Affiliate Network');
    });

    test('has navigation sort from config', function (): void {
        config(['filament-affiliate-network.navigation.sort' => 50]);

        expect(AffiliateOfferCategoryResource::getNavigationSort())->toBe(52);
    });

    test('has correct navigation icon', function (): void {
        expect(AffiliateOfferCategoryResource::getNavigationIcon())->toBe(Heroicon::OutlinedTag);
    });

    test('has correct model label', function (): void {
        expect(AffiliateOfferCategoryResource::getModelLabel())->toBe('Category');
    });

    test('has correct plural model label', function (): void {
        expect(AffiliateOfferCategoryResource::getPluralModelLabel())->toBe('Categories');
    });
});
