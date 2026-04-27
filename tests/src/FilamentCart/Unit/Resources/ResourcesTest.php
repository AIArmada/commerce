<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use Filament\Resources\Resource;

describe('Resources', function (): void {
    $resources = [
        CartConditionResource::class,
        CartItemResource::class,
        CartResource::class,
        ConditionResource::class,
    ];

    foreach ($resources as $resource) {
        it("has valid configuration for {$resource}", function () use ($resource): void {
            expect(is_subclass_of($resource, Resource::class))->toBeTrue();
            expect($resource::getModel())->not->toBeNull();
            // We can't easily test form() and table() without full Filament context mock
            // but we can test pages and relations if they return arrays
            expect($resource::getPages())->toBeArray();
            expect($resource::getRelations())->toBeArray();
        });
    }
});
