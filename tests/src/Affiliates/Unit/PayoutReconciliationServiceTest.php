<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');

    $this->service = app(PayoutReconciliationService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'RECON-' . uniqid(),
        'name' => 'Reconciliation Test Affiliate',
        'contact_email' => 'recon@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('PayoutReconciliationService', function (): void {
    describe('reconcilePayout', function (): void {
        test('returns false for unknown external status', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'unknown_status');

            expect($result)->toBeFalse();

            $payout->refresh();
            expect($payout->status)->toBeInstanceOf(ProcessingPayout::class);
        });

        test('returns false when status is unchanged', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => CompletedPayout::class,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'completed');

            expect($result)->toBeFalse();
        });

        test('updates payout status from external completed status', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 10000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'completed');

            expect($result)->toBeTrue();

            $payout->refresh();
            expect($payout->status)->toBeInstanceOf(CompletedPayout::class);
            expect($payout->paid_at)->not->toBeNull();
        });

        test('merges external data into metadata', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
                'metadata' => ['original' => 'data'],
            ]);

            $this->service->reconcilePayout($payout, 'completed', [
                'processor' => 'stripe',
                'fee' => 250,
            ]);

            $payout->refresh();
            expect($payout->metadata['original'])->toBe('data');
            expect($payout->metadata['reconciled_at'])->not->toBeNull();
            expect($payout->metadata['external_data']['processor'])->toBe('stripe');
            expect($payout->metadata['external_data']['fee'])->toBe(250);
        });

        test('handles case-insensitive status mapping', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'COMPLETED');

            expect($result)->toBeTrue();

            $payout->refresh();
            expect($payout->status)->toBeInstanceOf(CompletedPayout::class);
        });
    });

    describe('getPayoutsNeedingReconciliation', function (): void {
        test('returns payouts in processing status older than 1 hour', function (): void {
            // Create old processing payout with external reference
            $oldPayout = AffiliatePayout::create([
                'reference' => 'PAY-OLD-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-OLD',
            ]);

            // Manually update the timestamp
            AffiliatePayout::where('id', $oldPayout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            // Create recent payout (should not be returned)
            AffiliatePayout::create([
                'reference' => 'PAY-NEW-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => ProcessingPayout::class,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-NEW',
            ]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($oldPayout->id);
        });

        test('excludes completed payouts', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => CompletedPayout::class,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-123',
            ]);

            AffiliatePayout::where('id', $payout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toBeEmpty();
        });

        test('includes pending payouts', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PendingPayout::class,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-123',
            ]);

            AffiliatePayout::where('id', $payout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toHaveCount(1);
        });
    });

    describe('generateReport', function (): void {
        test('returns report structure', function (): void {
            $result = $this->service->generateReport();

            expect($result)->toHaveKeys(['period', 'summary', 'by_status', 'discrepancies']);
            expect($result['summary'])->toHaveKeys([
                'total_payouts',
                'total_amount_minor',
                'completed_amount_minor',
                'failed_amount_minor',
                'pending_amount_minor',
            ]);
        });

        test('groups by status', function (): void {
            AffiliatePayout::create([
                'reference' => 'PAY-1',
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => CompletedPayout::class,
                'method' => 'bank_transfer',
            ]);

            AffiliatePayout::create([
                'reference' => 'PAY-2',
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => CompletedPayout::class,
                'method' => 'bank_transfer',
            ]);

            AffiliatePayout::create([
                'reference' => 'PAY-3',
                'payee_type' => Affiliate::class,
                'payee_id' => $this->affiliate->id,
                'amount_minor' => 3000,
                'currency' => 'USD',
                'status' => PendingPayout::class,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->generateReport();

            expect($result['by_status'][CompletedPayout::value()])->toBe(2);
            expect($result['by_status'][PendingPayout::value()])->toBe(1);
        });

        test('handles empty payout result set', function (): void {
            $result = $this->service->generateReport();

            expect($result['summary']['total_payouts'])->toBe(0);
            expect($result['summary']['total_amount_minor'])->toBe(0);
            expect($result['discrepancies'])->toBeEmpty();
        });

        test('refuses to blend mixed-currency totals without rates', function (): void {
            foreach ([
                ['currency' => 'USD', 'total' => 10000, 'status' => CompletedPayout::class],
                ['currency' => 'MYR', 'total' => 47000, 'status' => CompletedPayout::class],
            ] as $index => $leg) {
                AffiliatePayout::create([
                    'reference' => 'PAY-MC-' . $index . '-' . uniqid(),
                    'payee_type' => Affiliate::class,
                    'payee_id' => $this->affiliate->id,
                    'total_minor' => $leg['total'],
                    'currency' => $leg['currency'],
                    'status' => $leg['status'],
                ]);
            }

            config(['affiliates.currency.default' => 'USD']);

            $result = $this->service->generateReport();

            expect($result['summary']['total_amount_minor'])->toBeNull()
                ->and($result['summary']['converted'])->toBeFalse()
                ->and($result['by_currency'])->toHaveKeys(['USD', 'MYR']);
        });

        test('converts mixed-currency totals when rates exist', function (): void {
            foreach ([
                ['currency' => 'USD', 'total' => 10000, 'status' => CompletedPayout::class],
                ['currency' => 'MYR', 'total' => 47000, 'status' => CompletedPayout::class],
            ] as $index => $leg) {
                AffiliatePayout::create([
                    'reference' => 'PAY-MC-' . $index . '-' . uniqid(),
                    'payee_type' => Affiliate::class,
                    'payee_id' => $this->affiliate->id,
                    'total_minor' => $leg['total'],
                    'currency' => $leg['currency'],
                    'status' => $leg['status'],
                ]);
            }

            config(['affiliates.currency.default' => 'USD']);
            (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.7], 'history' => []]))->save();

            $result = $this->service->generateReport();

            expect($result['summary']['total_amount_minor'])->toBe(20000)
                ->and($result['summary']['completed_amount_minor'])->toBe(20000)
                ->and($result['summary']['currency'])->toBe('USD')
                ->and($result['summary']['converted'])->toBeTrue();
        });
    });

    describe('auditAffiliateBalance', function (): void {
        test('returns audit structure', function (): void {
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'available_minor' => 5000,
                'pending_minor' => 0,
                'minimum_payout_minor' => 5000,
                'currency' => 'USD',
            ]);

            $result = $this->service->auditAffiliateBalance($this->affiliate, 'USD');

            expect($result)->toHaveKeys([
                'affiliate_id',
                'expected_available_minor',
                'actual_available_minor',
                'discrepancy_minor',
                'has_discrepancy',
                'approved_commissions_minor',
                'paid_out_minor',
                'pending_payouts_minor',
            ]);
        });

        test('detects balance discrepancy', function (): void {
            // Create approved conversion
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'value_minor' => 100000,
                'commission_minor' => 10000,
                'status' => 'approved',
                'commission_currency' => 'USD',
                'occurred_at' => now(),
            ]);

            ApplyConversionAccounting::run($conversion);

            // Balance doesn't match (should be 10000)
            $this->affiliate->balanceFor('USD')->update([
                'available_minor' => 5000, // Wrong!
            ]);

            $result = $this->service->auditAffiliateBalance($this->affiliate, 'USD');

            expect($result['expected_available_minor'])->toBe(10000);
            expect($result['actual_available_minor'])->toBe(5000);
            expect($result['discrepancy_minor'])->toBe(5000);
            expect($result['has_discrepancy'])->toBeTrue();
        });

        test('handles affiliate without balance', function (): void {
            $result = $this->service->auditAffiliateBalance($this->affiliate, 'USD');

            expect($result['actual_available_minor'])->toBe(0);
        });
    });
});
