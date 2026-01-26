<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use AIArmada\Affiliates\States\RejectedConversion;

test('AffiliateStatus states have correct labels', function (): void {
    expect(AffiliateStatus::all()->count())->toBe(5);

    expect(AffiliateStatus::labelFor(Draft::class))->toBe('Draft');
    expect(AffiliateStatus::labelFor(Pending::class))->toBe('Pending Approval');
    expect(AffiliateStatus::labelFor(Active::class))->toBe('Active');
    expect(AffiliateStatus::labelFor(Paused::class))->toBe('Paused');
    expect(AffiliateStatus::labelFor(Disabled::class))->toBe('Disabled');
});

test('CommissionType enum has correct cases and labels', function (): void {
    expect(CommissionType::cases())->toHaveCount(2);

    expect(CommissionType::Percentage->value)->toBe('percentage');
    expect(CommissionType::Percentage->label())->toBe('Percentage');

    expect(CommissionType::Fixed->value)->toBe('fixed');
    expect(CommissionType::Fixed->label())->toBe('Fixed Amount');
});

test('ConversionStatus states have correct values and labels', function (): void {
    expect(ConversionStatus::all()->count())->toBe(5);

    expect(PendingConversion::value())->toBe('pending');
    expect(ConversionStatus::labelFor(PendingConversion::class))->toBe('Pending Review');

    expect(QualifiedConversion::value())->toBe('qualified');
    expect(ConversionStatus::labelFor(QualifiedConversion::class))->toBe('Qualified');

    expect(ApprovedConversion::value())->toBe('approved');
    expect(ConversionStatus::labelFor(ApprovedConversion::class))->toBe('Approved');

    expect(RejectedConversion::value())->toBe('rejected');
    expect(ConversionStatus::labelFor(RejectedConversion::class))->toBe('Rejected');

    expect(PaidConversion::value())->toBe('paid');
    expect(ConversionStatus::labelFor(PaidConversion::class))->toBe('Paid Out');
});
