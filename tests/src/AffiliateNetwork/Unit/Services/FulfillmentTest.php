<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Adapters\Affiliates\EnginePayoutFulfillment;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
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
        $second = $fulfillment->fulfill($leg->fresh(), 'RUN-43');

        expect($first->adapter)->toBe('host-manual')
            ->and($first->reference)->toBe('RUN-42')
            ->and($second->reference)->toBe('RUN-42');
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

    test('only posted legs fulfill through the engine', function (): void {
        $link = fulfillmentLink('ful-aff-4');
        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'FUL-4', LegStatus::Provisional);

        app(EnginePayoutFulfillment::class)->fulfill($leg);
    })->throws(RuntimeException::class);

    test('suite binds the engine fulfillment adapter', function (): void {
        expect(app(Fulfillment::class))->toBeInstanceOf(EnginePayoutFulfillment::class);
    });
});
