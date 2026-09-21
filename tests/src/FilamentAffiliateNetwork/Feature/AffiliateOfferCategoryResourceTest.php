<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;

describe('AffiliateOfferCategoryResource', function (): void {
    test('returns pages array', function (): void {
        $pages = AffiliateOfferCategoryResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

});
