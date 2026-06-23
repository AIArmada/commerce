<?php

declare(strict_types=1);

use AIArmada\Events\Enums\PricingMode;

it('has three cases', function (): void {
    expect(PricingMode::cases())->toHaveCount(3);
});

it('identifies free-only', function (): void {
    expect(PricingMode::Free->isFreeOnly())->toBeTrue();
    expect(PricingMode::Paid->isFreeOnly())->toBeFalse();
    expect(PricingMode::Mixed->isFreeOnly())->toBeFalse();
});

it('provides labels', function (): void {
    expect(PricingMode::Free->label())->toBe('Free');
    expect(PricingMode::Paid->label())->toBe('Paid');
    expect(PricingMode::Mixed->label())->toBe('Mixed');
});

it('provides colors', function (): void {
    expect(PricingMode::Free->color())->toBe('success');
    expect(PricingMode::Paid->color())->toBe('danger');
    expect(PricingMode::Mixed->color())->toBe('warning');
});

it('provides options array', function (): void {
    $options = PricingMode::options();
    expect($options)->toHaveCount(3);
    expect($options['free'])->toBe('Free');
    expect($options['paid'])->toBe('Paid');
    expect($options['mixed'])->toBe('Mixed');
});
