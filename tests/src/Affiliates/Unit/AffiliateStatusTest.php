<?php

declare(strict_types=1);

use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;

it('can get affiliate status labels', function (): void {
    expect(AffiliateStatus::fromString(Draft::class)->label())->toBe('Draft')
        ->and(AffiliateStatus::fromString(Pending::class)->label())->toBe('Pending Approval')
        ->and(AffiliateStatus::fromString(Active::class)->label())->toBe('Active')
        ->and(AffiliateStatus::fromString(Paused::class)->label())->toBe('Paused')
        ->and(AffiliateStatus::fromString(Disabled::class)->label())->toBe('Disabled');
});

it('can get affiliate status descriptions', function (): void {
    expect(AffiliateStatus::fromString(Draft::class)->description())->toBe('Affiliate has not been submitted for approval')
        ->and(AffiliateStatus::fromString(Pending::class)->description())->toBe('Affiliate is awaiting approval')
        ->and(AffiliateStatus::fromString(Active::class)->description())->toBe('Affiliate can earn commissions')
        ->and(AffiliateStatus::fromString(Paused::class)->description())->toBe('Affiliate is temporarily inactive')
        ->and(AffiliateStatus::fromString(Disabled::class)->description())->toBe('Affiliate is disabled and cannot earn commissions');
});

it('can check affiliate status flags', function (): void {
    expect(AffiliateStatus::fromString(Active::class)->isActive())->toBeTrue()
        ->and(AffiliateStatus::fromString(Draft::class)->isDraft())->toBeTrue()
        ->and(AffiliateStatus::fromString(Pending::class)->isPending())->toBeTrue()
        ->and(AffiliateStatus::fromString(Paused::class)->isPaused())->toBeTrue()
        ->and(AffiliateStatus::fromString(Disabled::class)->isDisabled())->toBeTrue();
});
