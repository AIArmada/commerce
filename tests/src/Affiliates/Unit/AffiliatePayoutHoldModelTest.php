<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

describe('AffiliatePayoutHold Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'fraud_investigation',
            'notes' => 'Under review for suspicious activity',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold)->toBeInstanceOf(AffiliatePayoutHold::class)
            ->and($hold->reason)->toBe('fraud_investigation')
            ->and($hold->notes)->toBe('Under review for suspicious activity')
            ->and($hold->placed_by)->toBe('admin@example.com');
    });

    it('references an affiliate through its identity directory', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'verification_needed',
        ]);

        expect(app(AffiliateDirectory::class)->findById($hold->affiliate_id)?->id)->toBe($affiliate->id)
            ->and(method_exists($hold, 'affiliate'))->toBeFalse();
    });

    it('is active when not released and not expired', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-ACTIVE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'active_hold',
            'expires_at' => now()->addDays(30),
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    it('is not active when released', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-REL-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'released_hold',
            'released_at' => now(),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    it('is not active when expired', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-EXP-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'expired_hold',
            'expires_at' => now()->subDays(1),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    it('is active when no expiry set', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-NOEXP-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'indefinite_hold',
            'expires_at' => null,
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    it('can release hold', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-RELEASE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $administration = app(PayoutHoldAdministration::class);
        $holdId = $administration->place((string) $affiliate->getKey(), 'to_be_released');
        $hold = AffiliatePayoutHold::query()->findOrFail($holdId);

        expect($hold->isActive())->toBeTrue();

        expect(fn () => $administration->release((string) Str::uuid(), $holdId))->toThrow(ModelNotFoundException::class);
        expect($hold->fresh()->isActive())->toBeTrue();

        $administration->release((string) $affiliate->getKey(), $holdId, 'Reviewed');

        $hold->refresh();
        expect($hold->isActive())->toBeFalse()
            ->and($hold->released_at)->not->toBeNull()
            ->and($hold->notes)->toBe('Reviewed');

        $releasedAt = $hold->released_at;
        $administration->release((string) $affiliate->getKey(), $holdId, 'Duplicate release');
        expect($hold->fresh()->released_at->equalTo($releasedAt))->toBeTrue()
            ->and($hold->fresh()->notes)->toBe('Reviewed');
    });

    it('casts dates correctly', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-DATE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'date_test',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        expect($hold->expires_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($hold->expires_at->format('Y-m-d'))->toBe('2024-12-31');
    });

    it('uses correct table name from config', function (): void {
        $hold = new AffiliatePayoutHold;
        expect($hold->getTable())->toBe('affiliate_payout_holds');
    });
});
