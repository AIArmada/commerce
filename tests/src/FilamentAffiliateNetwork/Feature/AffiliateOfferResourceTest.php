<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Support\Icons\Heroicon;

describe('AffiliateOfferResource', function (): void {
    test('has correct model', function (): void {
        expect(AffiliateOfferResource::getModel())->toBe(AffiliateOffer::class);
    });

    test('returns pages array', function (): void {
        $pages = AffiliateOfferResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('has navigation group from config', function (): void {
        config(['filament-affiliate-network.navigation.group' => 'Affiliate Network']);

        expect(AffiliateOfferResource::getNavigationGroup())->toBe('Affiliate Network');
    });

    test('has navigation sort from config', function (): void {
        config(['filament-affiliate-network.navigation.sort' => 50]);

        expect(AffiliateOfferResource::getNavigationSort())->toBe(51);
    });

    test('has correct navigation icon', function (): void {
        expect(AffiliateOfferResource::getNavigationIcon())->toBe(Heroicon::OutlinedGift);
    });

    test('has correct model label', function (): void {
        expect(AffiliateOfferResource::getModelLabel())->toBe('Offer');
    });

    test('has correct plural model label', function (): void {
        expect(AffiliateOfferResource::getPluralModelLabel())->toBe('Offers');
    });

    test('has empty relation managers', function (): void {
        $relations = AffiliateOfferResource::getRelations();

        expect($relations)->toBeArray()->toBeEmpty();
    });
});
