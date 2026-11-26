<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;

it('can get affiliate status labels', function (): void {
    expect(AffiliateStatus::labelFor(Draft::class))->toBe('Draft')
        ->and(AffiliateStatus::labelFor(Pending::class))->toBe('Pending Approval')
        ->and(AffiliateStatus::labelFor(Active::class))->toBe('Active')
        ->and(AffiliateStatus::labelFor(Paused::class))->toBe('Paused')
        ->and(AffiliateStatus::labelFor(Disabled::class))->toBe('Disabled');
});

it('can get affiliate status descriptions', function (): void {
    expect(AffiliateStatus::descriptionFor(Draft::class))->toBe('Affiliate has not been submitted for approval')
        ->and(AffiliateStatus::descriptionFor(Pending::class))->toBe('Affiliate is awaiting approval')
        ->and(AffiliateStatus::descriptionFor(Active::class))->toBe('Affiliate can earn commissions')
        ->and(AffiliateStatus::descriptionFor(Paused::class))->toBe('Affiliate is temporarily inactive')
        ->and(AffiliateStatus::descriptionFor(Disabled::class))->toBe('Affiliate is disabled and cannot earn commissions');
});

it('can check affiliate status flags', function (): void {
    expect((new Active(new Affiliate))->isActive())->toBeTrue()
        ->and((new Draft(new Affiliate))->isDraft())->toBeTrue()
        ->and((new Pending(new Affiliate))->isPending())->toBeTrue()
        ->and((new Paused(new Affiliate))->isPaused())->toBeTrue()
        ->and((new Disabled(new Affiliate))->isDisabled())->toBeTrue();
});
