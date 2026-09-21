<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;

describe('AffiliateOfferApplicationResource', function (): void {
    test('returns pages array', function (): void {
        $pages = AffiliateOfferApplicationResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('view');
    });

    test('has empty relation managers', function (): void {
        $relations = AffiliateOfferApplicationResource::getRelations();

        expect($relations)->toBeArray()->toBeEmpty();
    });
});
