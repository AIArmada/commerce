<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Drivers\Affiliates\EnginePayoutFulfillmentDriver;
use AIArmada\AffiliateNetwork\Drivers\HostManualFulfillmentDriver;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\FulfillConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
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

function makeAffiliate(string $code = 'FUL'): Affiliate
{
    return Affiliate::create([
        'code' => $code . uniqid(),
        'name' => 'Fulfillment Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

describe('fulfillment', function (): void {
    test('host fulfillment records runs idempotently', function (): void {
        $affiliateId = (string) Str::uuid();
        $link = fulfillmentLink($affiliateId);
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: $affiliateId,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-1',
        );

        app()->instance(Fulfillment::class, new HostManualFulfillmentDriver);

        $first = app(FulfillConversionLeg::class)->execute((string) $leg->getKey(), 'RUN-42');
        $second = app(FulfillConversionLeg::class)->execute((string) $leg->getKey(), 'RUN-99');

        expect($first->metadata['fulfilled']['adapter'])->toBe('host-manual')
            ->and($first->metadata['fulfilled']['reference'])->toBe('RUN-42')
            ->and($second->metadata['fulfilled'])->toBe($first->metadata['fulfilled'])
            ->and($second->fulfilled_at)->not->toBeNull();
    });

    test('standalone recording leaves a posted leg unfulfilled until the host records a run', function (): void {
        $affiliateId = 'ful-host-' . uniqid();
        $link = fulfillmentLink($affiliateId);

        app()->instance(Fulfillment::class, new HostManualFulfillmentDriver);

        $leg = app(RecordNetworkConversion::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: $affiliateId,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-HOST-1',
        );

        expect(app(Fulfillment::class)->fulfillsAutomatically())->toBeFalse()
            ->and($leg->metadata['fulfilled'] ?? null)->toBeNull();

        $fulfilled = app(FulfillConversionLeg::class)->execute((string) $leg->getKey(), 'HOST-PAYOUT-1');

        expect($fulfilled->metadata['fulfilled']['reference'])->toBe('HOST-PAYOUT-1');
    });

    test('host fulfillment requires a posted leg and a non-empty reference', function (): void {
        $affiliateId = 'ful-host-guard-' . uniqid();
        $link = fulfillmentLink($affiliateId);
        $host = new HostManualFulfillmentDriver;

        $posted = app(PostConversionLeg::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: $affiliateId,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-HOST-GUARD',
        );

        expect(fn () => $host->fulfill($posted->toSnapshot()))->toThrow(InvalidArgumentException::class);

        $provisional = app(PostConversionLeg::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: $affiliateId,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-HOST-PROVISIONAL',
            status: LegStatus::Provisional,
        );

        expect(fn () => $host->fulfill($provisional->toSnapshot(), 'HOST-PAYOUT-2'))->toThrow(RuntimeException::class);
    });

    test('engine fulfillment posts the payout share to merchant books', function (): void {
        $affiliate = makeAffiliate();
        $link = fulfillmentLink((string) $affiliate->getKey(), ['network_fee_bp' => 2000]);

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-2',
        );

        $result = app(Fulfillment::class)->fulfill($leg->toSnapshot());

        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('external_reference', 'FUL-2')
            ->firstOrFail();

        expect($result->receipt?->adapter)->toBe('engine-payouts')
            ->and($conversion->commission_minor)->toBe(800)
            ->and($conversion->metadata['gross_commission_minor'])->toBe(1000)
            ->and($conversion->metadata['network_fee_minor'])->toBe(200);
    });

    test('engine fulfillment retries after unfulfillable legs link up', function (): void {
        $ghostId = 'ful-ghost-' . uniqid();
        $link = fulfillmentLink($ghostId);

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $link->offer_id,
            affiliateId: $ghostId,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FUL-3',
        );

        app(FulfillConversionLeg::class)->execute((string) $leg->getKey());

        expect($leg->fresh()->metadata['unfulfillable'])->toBe('merchant_not_found')
            ->and(AffiliateConversion::query()->exists())->toBeFalse();

        $affiliate = makeAffiliate('FUL2');
        $leg->forceFill(['affiliate_id' => (string) $affiliate->getKey()])->save();

        $fulfilled = app(FulfillConversionLeg::class)->execute((string) $leg->getKey());

        expect($fulfilled->metadata['fulfilled']['reference'])->not->toBeNull()
            ->and(AffiliateConversion::query()->count())->toBe(1);
    });

    test('engine fulfillment rejects missing, rejected, and altered merchant postings', function (): void {
        $link = fulfillmentLink('ful-invalid-' . uniqid());
        $responses = [
            ['response' => null, 'reason' => 'merchant_not_found'],
            ['response' => new NetworkPostedConversion('posted-1', 'FUL', 800, 'MYR', 'rejected'), 'reason' => 'merchant_rejected'],
            ['response' => new NetworkPostedConversion('posted-2', 'FUL', 799, 'MYR', 'pending'), 'reason' => 'commission_mismatch'],
            ['response' => new NetworkPostedConversion('posted-3', 'FUL', 800, 'USD', 'pending'), 'reason' => 'currency_mismatch'],
        ];

        foreach ($responses as $index => $case) {
            $leg = NetworkConversionLeg::factory()->create([
                'offer_id' => $link->offer_id,
                'affiliate_id' => 'ful-invalid-' . $index,
                'status' => LegStatus::Posted,
                'payout_minor' => 800,
                'commission_currency' => 'MYR',
                'external_reference' => 'FUL-INVALID-' . $index,
            ]);

            $ledger = mock(NetworkLedger::class);
            $ledger->shouldReceive('post')->once()->andReturn($case['response']);

            app()->instance(Fulfillment::class, new EnginePayoutFulfillmentDriver($ledger));

            $leg = app(FulfillConversionLeg::class)->execute((string) $leg->getKey());
            $metadata = $leg->metadata;

            expect($metadata['unfulfillable'])->toBe($case['reason'])
                ->and($metadata['fulfilled'] ?? null)->toBeNull()
                ->and($leg->fulfilled_at)->toBeNull();
        }
    });

    test('only posted legs fulfill through the engine', function (): void {
        $link = fulfillmentLink('ful-aff-4');
        $leg = NetworkConversionLeg::factory()->create([
            'offer_id' => $link->offer_id,
            'affiliate_id' => 'ful-aff-4',
            'status' => LegStatus::Provisional,
        ]);

        app(FulfillConversionLeg::class)->execute((string) $leg->getKey());
    })->throws(RuntimeException::class);

    test('suite binds the engine fulfillment adapter', function (): void {
        expect(app(Fulfillment::class))->toBeInstanceOf(EnginePayoutFulfillmentDriver::class);
    });
});
