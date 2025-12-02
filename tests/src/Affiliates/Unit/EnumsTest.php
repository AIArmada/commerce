<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;

test('AffiliateStatus enum has correct cases and labels', function (): void {
    expect(AffiliateStatus::cases())->toHaveCount(5);

    expect(AffiliateStatus::Draft->value)->toBe('draft');
    expect(AffiliateStatus::Draft->label())->toBe('Draft');

    expect(AffiliateStatus::Pending->value)->toBe('pending');
    expect(AffiliateStatus::Pending->label())->toBe('Pending Approval');

    expect(AffiliateStatus::Active->value)->toBe('active');
    expect(AffiliateStatus::Active->label())->toBe('Active');

    expect(AffiliateStatus::Paused->value)->toBe('paused');
    expect(AffiliateStatus::Paused->label())->toBe('Paused');

    expect(AffiliateStatus::Disabled->value)->toBe('disabled');
    expect(AffiliateStatus::Disabled->label())->toBe('Disabled');
});

test('CommissionType enum has correct cases and labels', function (): void {
    expect(CommissionType::cases())->toHaveCount(2);

    expect(CommissionType::Percentage->value)->toBe('percentage');
    expect(CommissionType::Percentage->label())->toBe('Percentage');

    expect(CommissionType::Fixed->value)->toBe('fixed');
    expect(CommissionType::Fixed->label())->toBe('Fixed Amount');
});

test('ConversionStatus enum has correct cases and labels', function (): void {
    expect(ConversionStatus::cases())->toHaveCount(5);

    expect(ConversionStatus::Pending->value)->toBe('pending');
    expect(ConversionStatus::Pending->label())->toBe('Pending Review');

    expect(ConversionStatus::Qualified->value)->toBe('qualified');
    expect(ConversionStatus::Qualified->label())->toBe('Qualified');

    expect(ConversionStatus::Approved->value)->toBe('approved');
    expect(ConversionStatus::Approved->label())->toBe('Approved');

    expect(ConversionStatus::Rejected->value)->toBe('rejected');
    expect(ConversionStatus::Rejected->label())->toBe('Rejected');

    expect(ConversionStatus::Paid->value)->toBe('paid');
    expect(ConversionStatus::Paid->label())->toBe('Paid Out');
});
