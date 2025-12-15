<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\RateQuoteData;

// ============================================
// RateQuoteData DTO Tests
// ============================================

it('creates rate quote with required fields', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
    );

    expect($quote->carrier)->toBe('jnt');
    expect($quote->service)->toBe('EZ');
    expect($quote->rate)->toBe(800);
    expect($quote->currency)->toBe('MYR');
    expect($quote->estimatedDays)->toBe(3);
});

it('creates rate quote with all fields', function (): void {
    $expiresAt = new DateTimeImmutable('2025-12-31');
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EXPRESS',
        rate: 1500,
        currency: 'MYR',
        estimatedDays: 1,
        serviceDescription: 'Express Next Day Delivery',
        quoteId: 'QUOTE-123',
        expiresAt: $expiresAt,
        calculatedLocally: false,
        note: 'Promotion applied',
    );

    expect($quote->serviceDescription)->toBe('Express Next Day Delivery');
    expect($quote->quoteId)->toBe('QUOTE-123');
    expect($quote->expiresAt)->toBe($expiresAt);
    expect($quote->calculatedLocally)->toBeFalse();
    expect($quote->note)->toBe('Promotion applied');
});

it('formats rate as currency string', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 850,
        currency: 'MYR',
        estimatedDays: 3,
    );

    $formatted = $quote->getFormattedRate();

    expect($formatted)->toBe('8.50 MYR');
});

it('identifies free shipping', function (): void {
    $freeQuote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 0,
        currency: 'MYR',
        estimatedDays: 3,
    );

    $paidQuote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 1000,
        currency: 'MYR',
        estimatedDays: 3,
    );

    expect($freeQuote->isFree())->toBeTrue();
    expect($paidQuote->isFree())->toBeFalse();
});

it('provides identifier combining carrier and service', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EXPRESS',
        rate: 1500,
        currency: 'MYR',
        estimatedDays: 1,
    );

    expect($quote->getIdentifier())->toBe('jnt:EXPRESS');
});

it('provides delivery estimate', function (): void {
    $singleDay = new RateQuoteData(
        carrier: 'jnt',
        service: 'EXPRESS',
        rate: 1500,
        currency: 'MYR',
        estimatedDays: 1,
    );

    $multiDay = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
    );

    expect($singleDay->getDeliveryEstimate())->toBe('1 business day');
    expect($multiDay->getDeliveryEstimate())->toBe('3 business days');
});

it('can create copy with modified rate', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
    );

    $modifiedQuote = $quote->withRate(0);

    expect($modifiedQuote->rate)->toBe(0);
    expect($modifiedQuote->carrier)->toBe('jnt'); // Other fields preserved
    expect($quote->rate)->toBe(800); // Original unchanged
});

it('can create copy with modified note', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
        note: null,
    );

    $modifiedQuote = $quote->withNote('Free shipping promotion');

    expect($modifiedQuote->note)->toBe('Free shipping promotion');
    expect($modifiedQuote->carrier)->toBe('jnt'); // Other fields preserved
    expect($modifiedQuote->rate)->toBe(800); // Other fields preserved
    expect($quote->note)->toBeNull(); // Original unchanged
});

it('returns estimated delivery date when available', function (): void {
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
        estimatedDeliveryDate: '2025-12-20',
    );

    expect($quote->getDeliveryEstimate())->toBe('2025-12-20');
});

it('preserves restrictions when creating with modified rate', function (): void {
    $restrictions = ['max_weight' => 30, 'max_value' => 100000];
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EZ',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
        restrictions: $restrictions,
    );

    $modifiedQuote = $quote->withRate(0);

    expect($modifiedQuote->restrictions)->toBe($restrictions);
});

it('preserves all fields when creating with modified note', function (): void {
    $expiresAt = new DateTimeImmutable('2025-12-31');
    $quote = new RateQuoteData(
        carrier: 'jnt',
        service: 'EXPRESS',
        rate: 1500,
        currency: 'MYR',
        estimatedDays: 1,
        estimatedDeliveryDate: '2025-12-20',
        serviceDescription: 'Express Delivery',
        restrictions: ['max_weight' => 30],
        calculatedLocally: true,
        quoteId: 'QUOTE-123',
        expiresAt: $expiresAt,
    );

    $modifiedQuote = $quote->withNote('Promo applied');

    expect($modifiedQuote->carrier)->toBe('jnt');
    expect($modifiedQuote->service)->toBe('EXPRESS');
    expect($modifiedQuote->rate)->toBe(1500);
    expect($modifiedQuote->currency)->toBe('MYR');
    expect($modifiedQuote->estimatedDays)->toBe(1);
    expect($modifiedQuote->estimatedDeliveryDate)->toBe('2025-12-20');
    expect($modifiedQuote->serviceDescription)->toBe('Express Delivery');
    expect($modifiedQuote->restrictions)->toBe(['max_weight' => 30]);
    expect($modifiedQuote->calculatedLocally)->toBeTrue();
    expect($modifiedQuote->quoteId)->toBe('QUOTE-123');
    expect($modifiedQuote->expiresAt)->toBe($expiresAt);
    expect($modifiedQuote->note)->toBe('Promo applied');
});
