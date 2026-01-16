<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;
use Filament\Support\Icons\Heroicon;

describe('AffiliateOfferApplicationResource', function (): void {
    test('has correct model', function (): void {
        expect(AffiliateOfferApplicationResource::getModel())->toBe(AffiliateOfferApplication::class);
    });

    test('returns pages array', function (): void {
        $pages = AffiliateOfferApplicationResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('view');
    });

    test('has navigation group from config', function (): void {
        config(['filament-affiliate-network.navigation.group' => 'Affiliate Network']);

        expect(AffiliateOfferApplicationResource::getNavigationGroup())->toBe('Affiliate Network');
    });

    test('has navigation sort from config', function (): void {
        config(['filament-affiliate-network.navigation.sort' => 50]);

        expect(AffiliateOfferApplicationResource::getNavigationSort())->toBe(53);
    });

    test('has correct navigation icon', function (): void {
        expect(AffiliateOfferApplicationResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentCheck);
    });

    test('has correct model label', function (): void {
        expect(AffiliateOfferApplicationResource::getModelLabel())->toBe('Application');
    });

    test('has correct plural model label', function (): void {
        expect(AffiliateOfferApplicationResource::getPluralModelLabel())->toBe('Applications');
    });

    test('has empty relation managers', function (): void {
        $relations = AffiliateOfferApplicationResource::getRelations();

        expect($relations)->toBeArray()->toBeEmpty();
    });
});
