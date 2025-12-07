<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\ReturnReason;

// ============================================
// ReturnReason Enum Tests
// ============================================

it('has all expected return reasons', function (): void {
    $reasons = ReturnReason::cases();

    expect($reasons)->toHaveCount(11);
    expect(ReturnReason::WrongItem)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::Damaged)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::Defective)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::NotAsDescribed)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::ChangedMind)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::DoesNotFit)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::BetterPrice)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::NoLongerNeeded)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::ArrivedTooLate)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::Unauthorized)->toBeInstanceOf(ReturnReason::class);
    expect(ReturnReason::Other)->toBeInstanceOf(ReturnReason::class);
});

it('returns readable labels for return reasons', function (): void {
    expect(ReturnReason::WrongItem->getLabel())->toBe('Wrong Item Received');
    expect(ReturnReason::Damaged->getLabel())->toBe('Item Damaged');
    expect(ReturnReason::Defective->getLabel())->toBe('Item Defective');
    expect(ReturnReason::NotAsDescribed->getLabel())->toBe('Not as Described');
    expect(ReturnReason::ChangedMind->getLabel())->toBe('Changed Mind');
});

it('correctly identifies seller fault reasons', function (): void {
    expect(ReturnReason::WrongItem->isSellerFault())->toBeTrue();
    expect(ReturnReason::Damaged->isSellerFault())->toBeTrue();
    expect(ReturnReason::Defective->isSellerFault())->toBeTrue();
    expect(ReturnReason::NotAsDescribed->isSellerFault())->toBeTrue();
    expect(ReturnReason::ChangedMind->isSellerFault())->toBeFalse();
    expect(ReturnReason::NoLongerNeeded->isSellerFault())->toBeFalse();
});

it('correctly identifies reasons requiring details', function (): void {
    expect(ReturnReason::Other->requiresDetails())->toBeTrue();
    expect(ReturnReason::NotAsDescribed->requiresDetails())->toBeTrue();
    expect(ReturnReason::Damaged->requiresDetails())->toBeFalse();
    expect(ReturnReason::ChangedMind->requiresDetails())->toBeFalse();
});
