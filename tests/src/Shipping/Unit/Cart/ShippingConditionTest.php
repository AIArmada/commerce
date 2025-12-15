<?php

declare(strict_types=1);

use AIArmada\Shipping\Cart\ShippingCondition;

// ============================================
// ShippingCondition Tests
// ============================================

it('creates shipping condition with required fields', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 1000,
    );

    expect($condition->asCartCondition())->not->toBeNull();
    expect($condition->asCartCondition()->getName())->toBe('Standard Shipping');
    expect($condition->asCartCondition()->getType())->toBe('shipping');
    expect($condition->asCartCondition()->getValue())->toBe(1000);
});

it('creates shipping condition with carrier attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Express Shipping',
        type: 'shipping',
        value: 1500,
        attributes: ['carrier' => 'jnt'],
    );

    expect($condition->getCarrier())->toBe('jnt');
});

it('creates shipping condition with service attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Express Shipping',
        type: 'shipping',
        value: 1500,
        attributes: ['service' => 'express'],
    );

    expect($condition->getService())->toBe('express');
});

it('creates shipping condition with estimated days', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 800,
        attributes: ['estimated_days' => 5],
    );

    expect($condition->getEstimatedDays())->toBe(5);
});

it('creates shipping condition with quote id', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 800,
        attributes: ['quote_id' => 'QUOTE123'],
    );

    expect($condition->getQuoteId())->toBe('QUOTE123');
});

it('detects free shipping when value is zero', function (): void {
    $condition = new ShippingCondition(
        name: 'Free Shipping',
        type: 'shipping',
        value: 0,
    );

    expect($condition->isFreeShipping())->toBeTrue();
});

it('detects not free shipping when value is greater than zero', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
    );

    expect($condition->isFreeShipping())->toBeFalse();
});

it('formats value as FREE when free shipping', function (): void {
    $condition = new ShippingCondition(
        name: 'Free Shipping',
        type: 'shipping',
        value: 0,
    );

    expect($condition->getFormattedValue())->toBe('FREE');
});

it('formats value with currency when not free shipping', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 1050, // 10.50 in cents
        attributes: ['currency' => 'MYR'],
    );

    expect($condition->getFormattedValue())->toBe('10.50 MYR');
});

it('uses MYR as default currency', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 1000,
    );

    expect($condition->getFormattedValue())->toBe('10.00 MYR');
});

it('returns null for missing carrier attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
    );

    expect($condition->getCarrier())->toBeNull();
});

it('returns null for missing service attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
    );

    expect($condition->getService())->toBeNull();
});

it('returns null for missing estimated days attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
    );

    expect($condition->getEstimatedDays())->toBeNull();
});

it('returns null for missing quote id attribute', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
    );

    expect($condition->getQuoteId())->toBeNull();
});

it('respects custom order in attributes', function (): void {
    $condition = new ShippingCondition(
        name: 'Standard Shipping',
        type: 'shipping',
        value: 500,
        attributes: ['order' => 100],
    );

    expect($condition->asCartCondition()->getOrder())->toBe(100);
});
