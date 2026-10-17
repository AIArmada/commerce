<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\EnginePayoutFulfillment;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\HostManualFulfillment;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;

function fulfillmentLink(string $affiliateId, array $offerAttributes = []): AffiliateOfferLink
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'ful-' . uniqid() . '.example']);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'landing_url' => 'https://ful.example/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ], $offerAttributes));

    return AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId($affiliateId)->create();
}

describe('fulfillment', function (): void {
    test('host fulfillment records runs idempotently', function (): void {
        $link = fulfillmentLink('ful-aff-1');
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-1');

        $fulfillment = new HostManualFulfillment;
        $first = $fulfillment->fulfill($leg, 'RUN-42');
        $second = $fulfillment->fulfill($leg->fresh());

        expect($first->adapter)->toBe('host-manual')
            ->and($first->reference)->toBe('RUN-42')
            ->and($second->toArray())->toBe($first->toArray());
    });

    test('standalone recording leaves a posted leg unfulfilled until the host records a run', function (): void {
        $link = fulfillmentLink('ful-host-' . uniqid());
        $host = new HostManualFulfillment;
        $record = new RecordNetworkConversion(app(NetworkBooks::class), $host);

        $leg = $record->execute($link, 10000, 'MYR', 'FUL-HOST-1');

        expect($host->fulfillsAutomatically())->toBeFalse()
            ->and($leg?->metadata['fulfilled'] ?? null)->toBeNull();

        $receipt = $host->fulfill($leg, 'HOST-PAYOUT-1');

        expect($receipt->reference)->toBe('HOST-PAYOUT-1')
            ->and($leg->fresh()->metadata['fulfilled']['reference'])->toBe('HOST-PAYOUT-1');
    });

    test('host fulfillment requires a posted leg and a non-empty reference', function (): void {
        $link = fulfillmentLink('ful-host-guard-' . uniqid());
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-HOST-GUARD');
        $host = new HostManualFulfillment;

        expect(fn () => $host->fulfill($leg))->toThrow(InvalidArgumentException::class);

        $provisional = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-HOST-PROVISIONAL', LegStatus::Provisional);

        expect(fn () => $host->fulfill($provisional, 'HOST-PAYOUT-2'))->toThrow(RuntimeException::class);
    });

    test('engine fulfillment posts the payout share to merchant books', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FUL' . uniqid(),
            'name' => 'Fulfillment Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);
        $link = fulfillmentLink((string) $affiliate->getKey(), ['network_fee_bp' => 2000]);
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-2');

        $receipt = app(EnginePayoutFulfillment::class)->fulfill($leg);

        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('external_reference', 'FUL-2')
            ->firstOrFail();

        expect($receipt->adapter)->toBe('engine-payouts')
            ->and($conversion->commission_minor)->toBe(800)
            ->and($conversion->metadata['gross_commission_minor'])->toBe(1000)
            ->and($conversion->metadata['network_fee_minor'])->toBe(200);
    });

    test('engine fulfillment retries after unfulfillable legs link up', function (): void {
        $link = fulfillmentLink('ful-ghost-' . uniqid());
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-3');

        $fulfillment = app(EnginePayoutFulfillment::class);
        $fulfillment->fulfill($leg);

        expect($leg->fresh()->metadata['unfulfillable'])->toBeTrue()
            ->and(AffiliateConversion::query()->exists())->toBeFalse();

        $affiliate = Affiliate::create([
            'code' => 'FUL2' . uniqid(),
            'name' => 'Late Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);
        $leg->forceFill(['affiliate_id' => (string) $affiliate->getKey()])->save();

        $receipt = $fulfillment->fulfill($leg->fresh());

        expect($receipt->reference)->not->toBeNull()
            ->and(AffiliateConversion::query()->count())->toBe(1);
    });

    test('engine fulfillment rejects missing, rejected, and altered merchant postings', function (): void {
        $link = fulfillmentLink('ful-invalid-' . uniqid());
        $responses = [
            ['response' => null, 'reason' => 'merchant_not_found'],
            ['response' => new NetworkPostedConversion('posted-1', 'FUL', 800, 'MYR', 'rejected'), 'reason' => 'merchant_rejected'],
            ['response' => new NetworkPostedConversion('posted-2', 'FUL', 799, 'MYR', 'pending'), 'reason' => 'commission_mismatch'],
            ['response' => new NetworkPostedConversion('posted-3', 'FUL', 1000, 'USD', 'pending'), 'reason' => 'currency_mismatch'],
        ];

        foreach ($responses as $index => $case) {
            $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-INVALID-' . $index);
            $ledger = mock(NetworkLedger::class);
            $ledger->shouldReceive('post')->once()->andReturn($case['response']);

            $receipt = (new EnginePayoutFulfillment($ledger))->fulfill($leg);
            $metadata = $leg->fresh()->metadata;

            expect($receipt)->toBeNull()
                ->and($metadata['unfulfillable'])->toBeTrue()
                ->and($metadata['reconciliation_required'])->toBeTrue()
                ->and($metadata['unfulfillable_reason'])->toBe($case['reason'])
                ->and($metadata['fulfilled'] ?? null)->toBeNull();
        }
    });

    test('only posted legs fulfill through the engine', function (): void {
        $link = fulfillmentLink('ful-aff-4');
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-4', LegStatus::Provisional);

        app(EnginePayoutFulfillment::class)->fulfill($leg);
    })->throws(RuntimeException::class);

    test('suite binds the engine fulfillment adapter', function (): void {
        expect(app(Fulfillment::class))->toBeInstanceOf(EnginePayoutFulfillment::class);
    });
});
