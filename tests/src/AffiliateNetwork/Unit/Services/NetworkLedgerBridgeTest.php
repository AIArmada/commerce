<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use Illuminate\Support\Facades\Event;

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
        Event::fake([NetworkConversionRecorded::class]);

        $service = app(OfferLinkService::class);
        $service->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');
        $service->recordConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');

        expect(AffiliateConversion::query()->where('source_ref', $this->link->id)->count())->toBe(1)
            ->and(NetworkConversionLeg::query()->where('link_id', $this->link->id)->count())->toBe(1)
            ->and($this->link->fresh()->conversions)->toBe(1);

        Event::assertDispatchedTimes(NetworkConversionRecorded::class, 1);
    });

    test('currency mismatch against offer pricing currency leaves cached revenue at zero', function (): void {
        $this->link->update(['currency' => null]);

        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'MYR', 'BRIDGE-FX-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-FX-1')->firstOrFail();

        expect($leg->commission_minor)->toBe(0)
            ->and($leg->commission_currency)->toBe('MYR')
            ->and($this->link->fresh()->revenue)->toBe(0);
    });

    test('records counter-only when no external reference is given', function (): void {
        Event::fake([NetworkConversionRecorded::class]);

        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD');
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD');

        expect(AffiliateConversion::query()->where('source_ref', $this->link->id)->count())->toBe(0)
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(2);

        Event::assertDispatchedTimes(NetworkConversionRecorded::class, 2);
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

    test('reconciliation flags active merchant rows without a posted network leg', function (): void {
        app(MerchantLedger::class)->postExternalConversion(new ExternalConversion(
            source: 'marketplace',
            sourceRef: (string) $this->link->getKey(),
            affiliateId: (string) $this->affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'CAD',
            externalReference: 'BRIDGE-ORPHAN-1',
            commissionMinor: 500,
        ));

        $report = app(NetworkLedgerReconciliationService::class)->reconcileLink($this->link->fresh());

        expect($report['match'])->toBeFalse()
            ->and($report['ledger']['by_currency']['CAD']['conversions'])->toBe(1)
            ->and($report['differences'])->toContain('merchant postings differ in CAD: legs 0/0 vs postings 1/500 (count/payout)');
    });

    test('merchant reversal reverses the network leg once and clears its balance', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-REVERSE-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-REVERSE-1')->firstOrFail();
        $conversion = AffiliateConversion::query()->where('external_reference', 'BRIDGE-REVERSE-1')->firstOrFail();
        $firstCompanion = ReverseAffiliateConversion::run($conversion, 'merchant refund');
        $secondCompanion = ReverseAffiliateConversion::run($conversion->fresh(), 'merchant refund');
        $balance = $this->affiliate->balanceFor('USD')->fresh();

        expect($firstCompanion->getKey())->toBe($secondCompanion->getKey())
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1)
            ->and($balance->holding_minor)->toBe(0)
            ->and($balance->available_minor)->toBe(0)
            ->and($balance->lifetime_earnings_minor)->toBe(0);
    });

    test('merchant void mirrors a non-paid network posting reversal', function (): void {
        app(OfferLinkService::class)->recordConversion($this->link, 10000, 'USD', 'BRIDGE-VOID-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-VOID-1')->firstOrFail();
        $conversion = AffiliateConversion::query()->where('external_reference', 'BRIDGE-VOID-1')->firstOrFail();

        VoidAffiliateConversion::run($conversion, 'merchant cancellation');

        expect($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1);
    });
});
