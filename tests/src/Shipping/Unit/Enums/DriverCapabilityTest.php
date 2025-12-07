<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\DriverCapability;

// ============================================
// DriverCapability Enum Tests
// ============================================

it('has all expected driver capabilities', function (): void {
    $capabilities = DriverCapability::cases();

    expect($capabilities)->toHaveCount(12);
    expect(DriverCapability::RateQuotes)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::LabelGeneration)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::Tracking)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::Webhooks)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::CashOnDelivery)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::Returns)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::AddressValidation)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::BatchOperations)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::PickupScheduling)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::InsuranceClaims)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::MultiPackage)->toBeInstanceOf(DriverCapability::class);
    expect(DriverCapability::InternationalShipping)->toBeInstanceOf(DriverCapability::class);
});

it('can convert capability to string via value', function (): void {
    expect(DriverCapability::RateQuotes->value)->toBe('rate_quotes');
    expect(DriverCapability::LabelGeneration->value)->toBe('label_generation');
    expect(DriverCapability::Tracking->value)->toBe('tracking');
    expect(DriverCapability::CashOnDelivery->value)->toBe('cash_on_delivery');
});
