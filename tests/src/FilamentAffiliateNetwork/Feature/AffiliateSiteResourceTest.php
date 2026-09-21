<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;

describe('AffiliateSiteResource', function (): void {
    test('returns pages array', function (): void {
        $pages = AffiliateSiteResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('has empty relation managers', function (): void {
        $relations = AffiliateSiteResource::getRelations();

        expect($relations)->toBeArray()->toBeEmpty();
    });
});
