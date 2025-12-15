<?php

declare(strict_types=1);

use AIArmada\Cart\AI\ProductRecommendation;

describe('ProductRecommendation', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-123',
            name: 'Test Product',
            type: 'frequently_bought',
            confidence: 0.85,
            reason: 'Customers often buy this together'
        );

        expect($recommendation->productId)->toBe('prod-123')
            ->and($recommendation->name)->toBe('Test Product')
            ->and($recommendation->type)->toBe('frequently_bought')
            ->and($recommendation->confidence)->toBe(0.85)
            ->and($recommendation->reason)->toBe('Customers often buy this together')
            ->and($recommendation->priceInCents)->toBe(0)
            ->and($recommendation->metadata)->toBeEmpty();
    });

    it('can be instantiated with all parameters', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-456',
            name: 'Premium Product',
            type: 'upsell',
            confidence: 0.9,
            reason: 'Premium version available',
            priceInCents: 5999,
            metadata: ['category' => 'electronics', 'sku' => 'PREM-001']
        );

        expect($recommendation->priceInCents)->toBe(5999)
            ->and($recommendation->metadata)->toBe(['category' => 'electronics', 'sku' => 'PREM-001']);
    });

    it('is high confidence when above threshold', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-123',
            name: 'Test',
            type: 'complementary',
            confidence: 0.75,
            reason: 'Good match'
        );

        expect($recommendation->isHighConfidence())->toBeTrue();
    });

    it('is high confidence when exactly at threshold', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-123',
            name: 'Test',
            type: 'personalized',
            confidence: 0.7,
            reason: 'Based on history'
        );

        expect($recommendation->isHighConfidence())->toBeTrue();
    });

    it('is not high confidence when below threshold', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-123',
            name: 'Test',
            type: 'trending',
            confidence: 0.5,
            reason: 'Trending now'
        );

        expect($recommendation->isHighConfidence())->toBeFalse();
    });

    it('converts to array correctly', function (): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-789',
            name: 'Great Product',
            type: 'complementary',
            confidence: 0.82,
            reason: 'Complements your cart',
            priceInCents: 1999,
            metadata: ['stock' => 100]
        );

        $array = $recommendation->toArray();

        expect($array)->toBeArray()
            ->and($array['product_id'])->toBe('prod-789')
            ->and($array['name'])->toBe('Great Product')
            ->and($array['type'])->toBe('complementary')
            ->and($array['confidence'])->toBe(0.82)
            ->and($array['confidence_percentage'])->toBe(82.0)
            ->and($array['reason'])->toBe('Complements your cart')
            ->and($array['price_in_cents'])->toBe(1999)
            ->and($array['metadata'])->toBe(['stock' => 100]);
    });

    it('supports all recommendation types', function (string $type): void {
        $recommendation = new ProductRecommendation(
            productId: 'prod-123',
            name: 'Test',
            type: $type,
            confidence: 0.5,
            reason: 'Test reason'
        );

        expect($recommendation->type)->toBe($type);
    })->with(['frequently_bought', 'complementary', 'personalized', 'upsell', 'trending']);
});
