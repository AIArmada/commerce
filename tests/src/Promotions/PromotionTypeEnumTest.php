<?php

declare(strict_types=1);

use AIArmada\Promotions\Enums\PromotionType;

describe('PromotionType Enum', function (): void {
    describe('formatValue method', function (): void {
        it('formats percentage value correctly', function (): void {
            expect(PromotionType::Percentage->formatValue(20))->toBe('20%');
            expect(PromotionType::Percentage->formatValue(10))->toBe('10%');
            expect(PromotionType::Percentage->formatValue(0))->toBe('0%');
        });

        it('formats fixed value correctly', function (): void {
            expect(PromotionType::Fixed->formatValue(1000))->toBe('$10.00');
            expect(PromotionType::Fixed->formatValue(2500))->toBe('$25.00');
            expect(PromotionType::Fixed->formatValue(99))->toBe('$0.99');
        });

    });
});
