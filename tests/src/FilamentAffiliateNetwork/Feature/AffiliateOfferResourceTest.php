<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager;

describe('AffiliateOfferResource', function (): void {
    test('returns pages array', function (): void {
        $pages = AffiliateOfferResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('registers the links relation manager', function (): void {
        expect(AffiliateOfferResource::getRelations())->toBe([
            LinksRelationManager::class,
        ]);
    });
});
