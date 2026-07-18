<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use Carbon\CarbonImmutable;

/**
 * Helper: create a conversion and apply accounting explicitly.
 */
function createConversionWithAccounting(array $data): AffiliateConversion
{
    $conversion = AffiliateConversion::create($data);
    ApplyConversionAccounting::run($conversion);

    return $conversion;
}

describe('AffiliateConversion Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'CONV' . uniqid(),
            'name' => 'Conversion Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-' . uniqid(),
            'subtotal_minor' => 50000,
            'value_minor' => 55000,
            'commission_minor' => 5500,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion)->toBeInstanceOf(AffiliateConversion::class);
        expect($conversion->subtotal_minor)->toBe(50000);
        expect($conversion->value_minor)->toBe(55000);
        expect($conversion->commission_minor)->toBe(5500);
    });

    test('records pending commissions in holding balance by default', function (): void {
        config(['affiliates.commissions.auto_approve' => false]);

        createConversionWithAccounting([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-BAL-' . uniqid(),
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        $balance = $this->affiliate->fresh()->balance;

        expect($balance)->not->toBeNull()
            ->and($balance?->holding_minor)->toBe(1000)
            ->and($balance?->available_minor)->toBe(0)
            ->and($balance?->lifetime_earnings_minor)->toBe(1000);
    });

    test('auto approved conversions become available immediately', function (): void {
        config(['affiliates.commissions.auto_approve' => true]);

        $conversion = createConversionWithAccounting([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-AUTO-' . uniqid(),
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ])->fresh();

        $balance = $this->affiliate->fresh()->balance;

        expect($conversion?->status->equals(ApprovedConversion::class))->toBeTrue()
            ->and($conversion?->approved_at)->not->toBeNull()
            ->and($balance)->not->toBeNull()
            ->and($balance?->holding_minor)->toBe(0)
            ->and($balance?->available_minor)->toBe(1000)
            ->and($balance?->lifetime_earnings_minor)->toBe(1000);
    });

    test('approved and paid transitions update affiliate balances', function (): void {
        config(['affiliates.commissions.auto_approve' => false]);

        $conversion = createConversionWithAccounting([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-TRANS-' . uniqid(),
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        $previousStatus = $conversion->status;
        $conversion->update(['status' => ApprovedConversion::class]);
        ApplyConversionAccounting::run($conversion, $previousStatus);

        $conversion = $conversion->fresh();
        $balance = $this->affiliate->fresh()->balance;

        expect($conversion?->approved_at)->not->toBeNull()
            ->and($balance)->not->toBeNull()
            ->and($balance?->holding_minor)->toBe(0)
            ->and($balance?->available_minor)->toBe(1000);

        $previousStatus = $conversion->status;
        $conversion?->update(['status' => PaidConversion::class]);
        ApplyConversionAccounting::run($conversion, $previousStatus);

        $balance = $this->affiliate->fresh()->balance;

        expect($balance?->available_minor)->toBe(0)
            ->and($balance?->lifetime_earnings_minor)->toBe(1000);
    });

    test('belongs to affiliate', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-REL-001',
            'value_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($conversion->affiliate->id)->toBe($this->affiliate->id);
    });

    test('belongs to attribution when set', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'conv123',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_attribution_id' => $attribution->id,
            'external_reference' => 'ORD-ATR-001',
            'value_minor' => 40000,
            'commission_minor' => 4000,
            'commission_currency' => 'USD',
            'status' => QualifiedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->attribution)->toBeInstanceOf(AffiliateAttribution::class);
        expect($conversion->attribution->id)->toBe($attribution->id);
    });

    test('belongs to payout when set', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-CONV-' . uniqid(),
            'status' => 'completed',
            'value_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'external_reference' => 'ORD-PAY-001',
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => PaidConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->payout)->toBeInstanceOf(AffiliatePayout::class);
        expect($conversion->payout->id)->toBe($payout->id);
    });

    test('stores external_reference', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'EXT-REF-001',
            'subtotal_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->external_reference)->toBe('EXT-REF-001');
    });

    test('currency accessor returns commission_currency', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-CUR-001',
            'value_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'EUR',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->commission_currency)->toBe('EUR');
    });

    test('casts status as state', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-ENUM-001',
            'value_minor' => 20000,
            'commission_minor' => 2000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->status)->toBeInstanceOf(ConversionStatus::class);
        expect($conversion->status->equals(PendingConversion::class))->toBeTrue();
    });

    test('supports all conversion statuses', function (): void {
        foreach ([PendingConversion::class, QualifiedConversion::class, ApprovedConversion::class, RejectedConversion::class, PaidConversion::class] as $status) {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'external_reference' => 'ORD-STATUS-' . $status::value(),
                'value_minor' => 10000,
                'commission_minor' => 1000,
                'commission_currency' => 'USD',
                'status' => $status,
                'occurred_at' => now(),
            ]);

            expect($conversion->status->equals($status))->toBeTrue();
        }
    });

    test('casts metadata as array', function (): void {
        $metadata = [
            'customer_id' => 'cust-123',
            'source' => 'web',
            'items_count' => 5,
        ];

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-META-001',
            'value_minor' => 35000,
            'commission_minor' => 3500,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        expect($conversion->metadata)->toBeArray();
        expect($conversion->metadata['customer_id'])->toBe('cust-123');
        expect($conversion->metadata['source'])->toBe('web');
        expect($conversion->metadata['items_count'])->toBe(5);
    });

    test('casts occurred_at as datetime', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-DATE-001',
            'value_minor' => 15000,
            'commission_minor' => 1500,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => '2024-06-15 14:30:00',
        ]);

        expect($conversion->occurred_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($conversion->occurred_at->format('Y-m-d'))->toBe('2024-06-15');
    });

    test('casts approved_at as datetime', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-APPR-001',
            'value_minor' => 45000,
            'commission_minor' => 4500,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => '2024-06-10 10:00:00',
            'approved_at' => '2024-06-11 12:00:00',
        ]);

        expect($conversion->approved_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($conversion->approved_at->format('Y-m-d'))->toBe('2024-06-11');
    });

    test('stores neutral subject and reference fields', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'subject_key' => 'event:123',
            'subject_instance' => 'share',
            'external_reference' => 'REG-123',
            'conversion_type' => 'registration',
            'value_minor' => 4200,
            'commission_minor' => 420,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->subject_key)->toBe('event:123')
            ->and($conversion->subject_instance)->toBe('share')
            ->and($conversion->external_reference)->toBe('REG-123')
            ->and($conversion->conversion_type)->toBe('registration')
            ->and($conversion->value_minor)->toBe(4200);
    });

    test('can store voucher code', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'voucher_code' => 'SUMMER20',
            'external_reference' => 'ORD-VOUCH-001',
            'value_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => QualifiedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->voucher_code)->toBe('SUMMER20');
    });

    test('can store channel', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-CHAN-001',
            'value_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'channel' => 'mobile_app',
            'occurred_at' => now(),
        ]);

        expect($conversion->channel)->toBe('mobile_app');
    });

    test('uses correct table name from config', function (): void {
        $conversion = new AffiliateConversion;

        expect($conversion->getTable())->toBe(config('affiliates.database.tables.conversions', 'affiliate_conversions'));
    });
});
