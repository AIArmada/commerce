<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutEventHistory;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRemoval;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

describe('AffiliatePayout Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'PAYOUT-TEST-' . uniqid(),
            'name' => 'Payout Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout)->toBeInstanceOf(AffiliatePayout::class)
            ->and($payout->status)->toBeInstanceOf(PendingPayout::class)
            ->and($payout->total_minor)->toBe(50000)
            ->and($payout->conversion_count)->toBe(5)
            ->and($payout->currency)->toBe('USD');
    });

    it('has polymorphic payee relationship', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->payee())->toBeInstanceOf(MorphTo::class)
            ->and($payout->payee)->toBeInstanceOf(Affiliate::class)
            ->and($payout->payee->id)->toBe($this->affiliate->id);
    });

    it('reads linked conversions through the owning feature', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect(app(PayoutConversionIntegrity::class)->hasConversions($payout->id, spl_object_id($payout->getConnection())))->toBeFalse();
    });

    it('reads payout events through the owning feature', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        $history = app(PayoutEventHistory::class);

        expect($history->hasEvents($payout->id))->toBeFalse();

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'to_status' => 'pending',
        ]);

        expect($history->hasEvents($payout->id))->toBeTrue()
            ->and($history->forPayout($payout->id))->toHaveCount(1);
    });

    it('returns affiliate accessor when payee is affiliate', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->payee)->toBeInstanceOf(Affiliate::class)
            ->and($payout->payee->id)->toBe($this->affiliate->id);
    });

    it('stores payout total in minor units', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 12345,
            'conversion_count' => 1,
            'currency' => 'USD',
        ]);

        expect($payout->total_minor)->toBe(12345);
    });

    it('stores external_reference as a native payout field', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'external_reference' => 'EXT-REF-123',
        ]);

        expect($payout->external_reference)->toBe('EXT-REF-123');
    });

    it('returns null external_reference when not set', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->external_reference)->toBeNull();
    });

    it('casts metadata as array', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'metadata' => ['key' => 'value'],
        ]);

        expect($payout->metadata)->toBeArray()
            ->and($payout->metadata['key'])->toBe('value');
    });

    it('removes events only when an unused payout is deleted', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 0,
            'conversion_count' => 0,
            'currency' => 'USD',
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        expect(fn () => $payout->delete())->toThrow(InvalidArgumentException::class);

        expect(app(PayoutRemoval::class)->deleteUnused((string) $payout->getKey()))->toBeTrue();

        expect(AffiliatePayoutEvent::where('affiliate_payout_id', $payout->id)->count())->toBe(0)
            ->and($payout->fresh())->toBeNull();
    });

    it('preserves payouts and their paid conversion references', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'status' => PendingPayout::class,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORDER-001',
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => PaidConversion::class,
        ]);

        expect(fn () => app(PayoutRemoval::class)->deleteUnused((string) $payout->getKey()))
            ->toThrow(ValidationException::class)
            ->and(fn () => $payout->delete())->toThrow(InvalidArgumentException::class)
            ->and($payout->fresh())->not->toBeNull()
            ->and($conversion->fresh()->affiliate_payout_id)->toBe($payout->id);
    });

    it('uses correct table name from config', function (): void {
        $payout = new AffiliatePayout;

        expect($payout->getTable())->toBe('affiliate_payouts');
    });
});
