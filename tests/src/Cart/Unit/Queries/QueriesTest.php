<?php

declare(strict_types=1);

use AIArmada\Cart\Queries\GetAbandonedCartsQuery;
use AIArmada\Cart\Queries\GetCartSummaryQuery;
use AIArmada\Cart\Queries\SearchCartsQuery;

describe('GetAbandonedCartsQuery', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $olderThan = new DateTimeImmutable('2024-01-15 10:00:00');

        $query = new GetAbandonedCartsQuery(olderThan: $olderThan);

        expect($query->olderThan)->toBe($olderThan)
            ->and($query->minValueCents)->toBeNull()
            ->and($query->limit)->toBe(100);
    });

    it('can be instantiated with all parameters', function (): void {
        $olderThan = new DateTimeImmutable('2024-01-15 10:00:00');

        $query = new GetAbandonedCartsQuery(
            olderThan: $olderThan,
            minValueCents: 5000,
            limit: 50
        );

        expect($query->olderThan)->toBe($olderThan)
            ->and($query->minValueCents)->toBe(5000)
            ->and($query->limit)->toBe(50);
    });
});

describe('SearchCartsQuery', function (): void {
    it('can be instantiated with default values', function (): void {
        $query = new SearchCartsQuery;

        expect($query->identifier)->toBeNull()
            ->and($query->instance)->toBeNull()
            ->and($query->createdAfter)->toBeNull()
            ->and($query->createdBefore)->toBeNull()
            ->and($query->minItems)->toBeNull()
            ->and($query->limit)->toBe(50)
            ->and($query->offset)->toBe(0);
    });

    it('can be instantiated with all parameters', function (): void {
        $after = new DateTimeImmutable('2024-01-01');
        $before = new DateTimeImmutable('2024-12-31');

        $query = new SearchCartsQuery(
            identifier: 'user-123',
            instance: 'shopping',
            createdAfter: $after,
            createdBefore: $before,
            minItems: 3,
            limit: 25,
            offset: 10
        );

        expect($query->identifier)->toBe('user-123')
            ->and($query->instance)->toBe('shopping')
            ->and($query->createdAfter)->toBe($after)
            ->and($query->createdBefore)->toBe($before)
            ->and($query->minItems)->toBe(3)
            ->and($query->limit)->toBe(25)
            ->and($query->offset)->toBe(10);
    });
});

describe('GetCartSummaryQuery', function (): void {
    it('can be instantiated', function (): void {
        $query = new GetCartSummaryQuery(
            cartId: 'cart-uuid-123'
        );

        expect($query->cartId)->toBe('cart-uuid-123');
    });
});
