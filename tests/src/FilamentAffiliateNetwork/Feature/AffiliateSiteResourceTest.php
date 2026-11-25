<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\RelationManagers\StatementsRelationManager;

describe('AffiliateSiteResource', function (): void {
    test('returns pages array', function (): void {
        $pages = AffiliateSiteResource::getPages();

        expect($pages)
            ->toBeArray()
            ->toHaveKey('index')
            ->toHaveKey('create')
            ->toHaveKey('edit');
    });

    test('registers the statements relation manager', function (): void {
        $relations = AffiliateSiteResource::getRelations();

        expect($relations)->toBeArray()->toBe([StatementsRelationManager::class]);
    });
});
