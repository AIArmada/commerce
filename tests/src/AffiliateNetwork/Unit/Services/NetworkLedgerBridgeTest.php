<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RecordLinkCounters;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\LedgerReconciliationQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Features\Conversions\Actions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Actions\VoidAffiliateConversion;
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

function recordBridgeConversion(AffiliateOfferLink $link, int $revenueMinor, string $currency, string $reference): NetworkConversionLeg
{
    return app(RecordNetworkConversion::class)->execute(
        offerId: (string) $link->offer_id,
        affiliateId: (string) $link->affiliate_id,
        revenueMinor: $revenueMinor,
        currency: $currency,
        externalReference: $reference,
        linkId: (string) $link->getKey(),
    );
}

describe('network ledger bridge', function (): void {
    test('posts a book leg and fulfills the creator share into merchant books', function (): void {
        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-ORDER-1')->firstOrFail();
        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('source_ref', (string) $this->link->getKey())
            ->firstOrFail();

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->payout_minor)->toBe(1000)
            ->and($conversion->commission_minor)->toBe(1000)
            ->and($conversion->commission_currency)->toBe('USD')
            ->and($conversion->value_minor)->toBe(10000)
            ->and($conversion->external_reference)->toBe('BRIDGE-ORDER-1')
            ->and(AffiliateBalance::query()->where('affiliate_id', $this->affiliate->getKey())->where('currency', 'USD')->first()?->holding_minor)->toBe(1000);
    });

    test('fulfills the payout share when a network fee applies', function (): void {
        $this->offer->forceFill(['network_fee_bp' => 2000])->save();

        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-FEE');

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

        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-2');

        $conversion = AffiliateConversion::query()
            ->where('origin', 'marketplace')
            ->where('source_ref', (string) $this->link->getKey())
            ->firstOrFail();

        expect($conversion->commission_minor)->toBe(500)
            ->and($conversion->commission_currency)->toBe('USD');
    });

    test('ledger posting is idempotent on the external reference', function (): void {
        Event::fake([NetworkConversionRecorded::class]);

        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');
        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-3');

        expect(AffiliateConversion::query()->where('source_ref', (string) $this->link->getKey())->count())->toBe(1)
            ->and(NetworkConversionLeg::query()->where('link_id', (string) $this->link->getKey())->count())->toBe(1)
            ->and($this->link->fresh()->conversions)->toBe(1);

        Event::assertDispatchedTimes(NetworkConversionRecorded::class, 1);
    });

    test('currency mismatch against offer pricing currency keeps commission at zero', function (): void {
        $this->link->update(['currency' => null]);

        recordBridgeConversion($this->link, 10000, 'MYR', 'BRIDGE-FX-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-FX-1')->firstOrFail();

        expect($leg->commission_minor)->toBe(0)
            ->and($leg->commission_currency)->toBe('MYR')
            ->and($this->link->fresh()->revenue)->toBe(10000);
    });

    test('records counter-only without a leg or merchant posting', function (): void {
        Event::fake([NetworkConversionRecorded::class]);

        app(RecordLinkCounters::class)->execute((string) $this->link->getKey(), 10000, 1);
        app(RecordLinkCounters::class)->execute((string) $this->link->getKey(), 10000, 1);

        expect(AffiliateConversion::query()->where('source_ref', (string) $this->link->getKey())->count())->toBe(0)
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(2);

        Event::assertNotDispatched(NetworkConversionRecorded::class);
    });

    test('reconciliation matches a fully posted link', function (): void {
        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-ORDER-4');

        $report = app(LedgerReconciliationQuery::class)->reconcileLink((string) $this->link->getKey());

        expect($report['match'])->toBeTrue()
            ->and($report['network']['conversions'])->toBe(1)
            ->and($report['legs']['posted'])->toBe(1);
    });

    test('reconciliation flags a link with missing ledger rows', function (): void {
        app(RecordLinkCounters::class)->execute((string) $this->link->getKey(), 10000, 1);

        $report = app(LedgerReconciliationQuery::class)->reconcileLink((string) $this->link->getKey());

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

        $report = app(LedgerReconciliationQuery::class)->reconcileLink((string) $this->link->getKey());

        expect($report['match'])->toBeFalse()
            ->and($report['differences'])->toContain('merchant postings differ in CAD: legs 0/0 vs postings 1/500 (count/payout)');
    });

    test('merchant reversal reverses the network leg once and clears its balance', function (): void {
        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-REVERSE-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-REVERSE-1')->firstOrFail();
        $conversion = AffiliateConversion::query()->where('external_reference', 'BRIDGE-REVERSE-1')->firstOrFail();
        $firstCompanion = app(ReverseAffiliateConversion::class)->handle($conversion, 'merchant refund');
        $secondCompanion = app(ReverseAffiliateConversion::class)->handle($conversion->fresh(), 'merchant refund');
        $balance = AffiliateBalance::query()->where('affiliate_id', $this->affiliate->getKey())->where('currency', 'USD')->first()->fresh();

        expect($firstCompanion->getKey())->toBe($secondCompanion->getKey())
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1)
            ->and($balance->holding_minor)->toBe(0)
            ->and($balance->available_minor)->toBe(0)
            ->and($balance->lifetime_earnings_minor)->toBe(0);
    });

    test('merchant void mirrors a non-paid network posting reversal', function (): void {
        recordBridgeConversion($this->link, 10000, 'USD', 'BRIDGE-VOID-1');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'BRIDGE-VOID-1')->firstOrFail();
        $conversion = AffiliateConversion::query()->where('external_reference', 'BRIDGE-VOID-1')->firstOrFail();

        app(VoidAffiliateConversion::class)->handle($conversion, 'merchant cancellation');

        expect($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1);
    });
});
