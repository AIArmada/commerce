<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;

beforeEach(function (): void {
    $this->site = AffiliateSite::factory()->verified()->create(['domain' => 'bridge-example.com']);
    $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
        'landing_url' => 'https://bridge-example.com/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'USD',
    ]);
    $this->affiliate = Affiliate::create([
        'code' => 'BRIDGE' . uniqid(),
        'name' => 'Bridge Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
    $this->link = AffiliateOfferLink::factory()
        ->forOffer($this->offer)
        ->forAffiliateId((string) $this->affiliate->getKey())
        ->create(['currency' => 'USD']);
});

describe('NetworkLedgerBridge', function (): void {
    test('posts a book leg and fulfills the creator share into merchant books', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-ORDER-1')->firstOrFail();
        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('source_ref', $this->link->id)
            ->firstOrFail();

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->payout_minor)->toBe(1000)
            ->and($conversion->commission_minor)->toBe(1000)
            ->and($conversion->commission_currency)->toBe('USD')
            ->and($conversion->value_minor)->toBe(10000)
            ->and($conversion->external_reference)->toBe('BRIDGE-ORDER-1')
            ->and($this->affiliate->balanceFor('USD')?->holding_minor)->toBe(1000);
    });

    test('fulfills the payout share when a network fee applies', function (): void {
        $this->offer->forceFill(['network_fee_bp' => 2000])->save();

        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-FEE');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-ORDER-FEE')->firstOrFail();
        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('external_reference', 'BRIDGE-ORDER-FEE')
            ->firstOrFail();

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->fee_minor)->toBe(200)
            ->and($leg->payout_minor)->toBe(800)
            ->and($conversion->commission_minor)->toBe(800)
            ->and($conversion->metadata['network_fee_minor'])->toBe(200);
    });

    test('posts fixed commissions from the offer terms', function (): void {
        $this->offer->forceFill(['rate_base_bp' => null, 'rate_fixed_minor' => 500])->save();

        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-2');

        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('source_ref', $this->link->id)
            ->firstOrFail();

        expect($conversion->commission_minor)->toBe(500)
            ->and($conversion->commission_currency)->toBe('USD');
    });

    test('ledger posting is idempotent on the external reference', function (): void {
        $service = app(OfferLinkService::class);
        $service->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');
        $service->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');

        expect(AffiliateConversion::query()->where('source_ref', $this->link->id)->count())->toBe(1)
            ->and(NetworkConversionLeg::query()->where('link_id', $this->link->id)->count())->toBe(1)
            ->and($this->link->fresh()->conversions)->toBe(2);
    });

    test('records counter-only when no external reference is given', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD');

        expect(AffiliateConversion::query()->where('source_ref', $this->link->id)->count())->toBe(0)
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(1);
    });

    test('reconciliation matches a fully posted link', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-4');

        $report = app(NetworkLedgerReconciliationService::class)->reconcileLink($this->link->fresh());

        expect($report['match'])->toBeTrue()
            ->and($report['network']['conversions'])->toBe(1)
            ->and($report['ledger']['conversions'])->toBe(1);
    });

    test('reconciliation flags a link with missing ledger rows', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD');

        $report = app(NetworkLedgerReconciliationService::class)->reconcileLink($this->link->fresh());

        expect($report['match'])->toBeFalse()
            ->and($report['differences'])->not->toBe([]);
    });
});
