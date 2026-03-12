<?php

declare(strict_types=1);

use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use Illuminate\Database\Eloquent\Builder;

describe('PromotionResource', function (): void {
    describe('model', function (): void {
        it('uses the Filament Promotion model', function (): void {
            expect(PromotionResource::getModel())->toBe(Promotion::class);
        });
    });

    describe('navigation', function (): void {
        it('has navigation label', function (): void {
            expect(PromotionResource::getNavigationLabel())->toBe('Promotions');
        });

        it('has model label', function (): void {
            expect(PromotionResource::getModelLabel())->toBe('Promotion');
        });

        it('has plural model label', function (): void {
            expect(PromotionResource::getPluralModelLabel())->toBe('Promotions');
        });
    });

    describe('pages', function (): void {
        it('has index page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('index');
        });

        it('has create page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('create');
        });

        it('has view page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('view');
        });

        it('has edit page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('edit');
        });
    });

    describe('eloquent query', function (): void {
        it('returns query builder', function (): void {
            $query = PromotionResource::getEloquentQuery();

            expect($query)->toBeInstanceOf(Builder::class);
        });
    });

    describe('navigation badge', function (): void {
        it('returns null when no active promotions', function (): void {
            Promotion::factory()->inactive()->count(3)->create();

            expect(PromotionResource::getNavigationBadge())->toBeNull();
        });

        it('returns count when active promotions exist', function (): void {
            Promotion::factory()->active()->count(5)->create();

            expect(PromotionResource::getNavigationBadge())->toBe('5');
        });
    });

    describe('configuration', function (): void {
        it('has correct navigation group', function (): void {
            expect(PromotionResource::getNavigationGroup())->toBe(config('filament-promotions.navigation_group'));
        });

        it('has navigation badge color', function (): void {
            expect(PromotionResource::getNavigationBadgeColor())->toBe('success');
        });

        it('has tenant ownership relationship name', function (): void {
            $reflection = new ReflectionClass(PromotionResource::class);
            $property = $reflection->getProperty('tenantOwnershipRelationshipName');
            $property->setAccessible(true);

            expect($property->getValue())->toBe('owner');
        });
    });
});
