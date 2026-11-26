<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\OfferDiscoveryFilter;
use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Events\ConversionLocked;
use AIArmada\AffiliateNetwork\Exceptions\CapExceededException;
use AIArmada\AffiliateNetwork\Exceptions\RestrictionViolatedException;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EvaluateOfferRestrictions;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\OfferDiscoveryQuery;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\OfferMetricsQuery;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\CheckOfferCaps;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\FulfillConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

function powerAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'PWR' . uniqid(),
        'handle' => 'power-' . uniqid(),
        'name' => 'Power Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

function powerOffer(array $attributes = []): AffiliateOffer
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'power-' . uniqid() . '.example']);

    return AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
        'requires_approval' => false,
        'network_fee_bp' => null,
    ], $attributes));
}

function powerPost(AffiliateOffer $offer, Affiliate $affiliate, string $reference, int $revenue = 10000, array $extra = []): NetworkConversionLeg
{
    return app(PostConversionLeg::class)->execute(
        offerId: (string) $offer->getKey(),
        affiliateId: (string) $affiliate->getKey(),
        revenueMinor: $revenue,
        currency: 'MYR',
        externalReference: $reference,
        linkId: $extra['link_id'] ?? null,
        linkCode: $extra['link_code'] ?? null,
        couponCode: $extra['coupon_code'] ?? null,
        couponId: $extra['coupon_id'] ?? null,
        eventType: $extra['event_type'] ?? 'purchase',
        status: $extra['status'] ?? LegStatus::Posted,
    );
}

describe('power feature: validation and locking window', function (): void {
    test('a pending leg locks after maturity, auto-fulfills and emits ConversionLocked', function (): void {
        Event::fake([ConversionLocked::class]);

        $offer = powerOffer(['locking_days' => 7]);
        $affiliate = powerAffiliate();
        $leg = powerPost($offer, $affiliate, 'LOCK-1', 50000);

        expect($leg->status)->toBe(LegStatus::Pending)
            ->and($leg->locks_at)->not->toBeNull();

        $locked = app(LockMaturedLegs::class)->execute(CarbonImmutable::now()->addDays(8));

        $fresh = $leg->fresh();

        expect($locked)->toContain((string) $leg->getKey())
            ->and($fresh->status)->toBe(LegStatus::Posted)
            ->and($fresh->locked_at)->not->toBeNull();

        Event::assertDispatched(ConversionLocked::class);
    });

    test('the lock-matured-legs command locks matured legs', function (): void {
        $offer = powerOffer(['locking_days' => 3]);
        $leg = powerPost($offer, powerAffiliate(), 'LOCK-CMD', 20000);

        $this->artisan('affiliate-network:lock-matured', [
            '--as-of' => CarbonImmutable::now()->addDays(5)->toIso8601String(),
        ])->assertSuccessful();

        expect($leg->fresh()->status)->toBe(LegStatus::Posted);
    });

    test('creator balances expose pending, payable, paid and clawback buckets', function (): void {
        $affiliate = powerAffiliate();

        $pendingOffer = powerOffer(['locking_days' => 5]);
        $pending = powerPost($pendingOffer, $affiliate, 'BAL-PENDING', 10000);

        $posted = powerPost(powerOffer(), $affiliate, 'BAL-POSTED', 10000);
        app(FulfillConversionLeg::class)->execute((string) $posted->getKey());

        $reverseTarget = powerPost(powerOffer(), $affiliate, 'BAL-REVERSE', 30000);
        app(FulfillConversionLeg::class)->execute((string) $reverseTarget->getKey());
        app(ReverseConversionLeg::class)->execute((string) $reverseTarget->getKey(), 'refund');

        $balances = app(CreatorBalancesQuery::class)->for((string) $affiliate->getKey())['MYR'];

        expect($pending->fresh()->status)->toBe(LegStatus::Pending)
            ->and($balances->pendingMinor)->toBe($pending->payout_minor)
            ->and($balances->paidMinor)->toBe($posted->payout_minor)
            ->and($balances->reversedMinor)->toBe(0)
            ->and($balances->netPayableMinor)->toBe($balances->payableMinor - $reverseTarget->payout_minor);
    });
});

describe('power feature: merchant billing and statements', function (): void {
    test('statement lifecycle generates, issues, pays and voids', function (): void {
        $offer = powerOffer();
        $leg = powerPost($offer, powerAffiliate(), 'STMT-1', 50000);
        $site = $offer->site;

        $statement = app(GenerateMerchantStatement::class)->execute(
            (string) $site->getKey(),
            'MYR',
            CarbonImmutable::now()->subDay()->startOfDay(),
            CarbonImmutable::now()->addDay()->endOfDay(),
        );

        expect($statement->status)->toBe(SettlementStatus::Draft)
            ->and($statement->leg_count)->toBe(1)
            ->and($leg->fresh()->statement_id)->toBe($statement->getKey());

        $issued = app(IssueMerchantStatement::class)->execute((string) $statement->getKey());
        expect($issued->status)->toBe(SettlementStatus::Issued)
            ->and($issued->due_at)->not->toBeNull();

        $paid = app(MarkMerchantStatementPaid::class)->execute((string) $statement->getKey(), 'WIRE-1');
        expect($paid->status)->toBe(SettlementStatus::Paid)
            ->and($paid->payment_reference)->toBe('WIRE-1');

        $second = powerPost($offer, powerAffiliate(), 'STMT-2', 10000);
        $voidable = app(GenerateMerchantStatement::class)->execute(
            (string) $site->getKey(),
            'MYR',
            CarbonImmutable::now()->subDay()->startOfDay(),
            CarbonImmutable::now()->addDay()->endOfDay(),
        );
        app(VoidMerchantStatement::class)->execute((string) $voidable->getKey());

        expect($voidable->fresh()->status)->toBe(SettlementStatus::Void)
            ->and($second->fresh()->statement_id)->toBeNull();
    });

    test('pay-when-paid gate blocks fulfilment until the statement is paid', function (): void {
        $offer = powerOffer();
        $site = $offer->site;
        $site->update(['require_settled_for_payout' => true]);

        $leg = powerPost($offer, powerAffiliate(), 'PWP-1', 40000);

        $statement = app(GenerateMerchantStatement::class)->execute(
            (string) $site->getKey(),
            'MYR',
            CarbonImmutable::now()->subDay()->startOfDay(),
            CarbonImmutable::now()->addDay()->endOfDay(),
        );

        expect(fn () => app(FulfillConversionLeg::class)->execute((string) $leg->getKey()))
            ->toThrow(RuntimeException::class, 'Pay-when-paid');

        app(MarkMerchantStatementPaid::class)->execute((string) $statement->getKey());

        $fulfilled = app(FulfillConversionLeg::class)->execute((string) $leg->getKey());

        expect($fulfilled->fulfilled_at)->not->toBeNull();
    });

    test('prepaid balance is deducted on conversion and refunded on reversal', function (): void {
        $offer = powerOffer();
        $site = $offer->site;
        $site->update(['prepaid_balance_minor' => 100000]);

        $leg = powerPost($offer, powerAffiliate(), 'PREPAID-1', 50000);

        $afterConversion = (int) $site->fresh()->prepaid_balance_minor;
        expect($afterConversion)->toBe(100000 - $leg->commission_minor);

        app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'refund');

        expect((int) $site->fresh()->prepaid_balance_minor)->toBe(100000);
    });

    test('monthly spend cap is enforced', function (): void {
        $offer = powerOffer();
        $site = $offer->site;
        $site->update(['monthly_spend_cap_minor' => 500]);

        $first = powerPost($offer, powerAffiliate(), 'CAP-1', 10000);

        expect($first->commission_minor)->toBe(1000);

        expect(fn () => powerPost($offer, powerAffiliate(), 'CAP-2', 10000))
            ->toThrow(CapExceededException::class);
    });
});

describe('power feature: offer caps and smart fallback', function (): void {
    test('daily total and per-affiliate conversion caps stop new legs', function (string $capColumn): void {
        $offer = powerOffer([$capColumn => 1]);
        $affiliate = powerAffiliate();

        powerPost($offer, $affiliate, 'OFCAP-1', 10000);

        expect(app(CheckOfferCaps::class)->checkReason((string) $offer->getKey(), (string) $affiliate->getKey(), 0))
            ->toContain('cap');
    })->with(['daily_conversion_cap', 'total_conversion_cap', 'per_affiliate_conversion_cap']);

    test('monthly and total payout caps stop new legs', function (string $capColumn): void {
        $offer = powerOffer([$capColumn => 100]);
        $affiliate = powerAffiliate();

        powerPost($offer, $affiliate, 'PAYCAP-1', 100000);

        expect(app(CheckOfferCaps::class)->checkReason((string) $offer->getKey()))->toContain('cap');
    })->with(['monthly_payout_cap_minor', 'total_payout_cap_minor']);

    test('prepaid balance blocks a payout that exceeds the remaining balance', function (): void {
        $offer = powerOffer();
        $site = $offer->site;
        $site->update(['prepaid_balance_minor' => 500]);

        expect(app(CheckOfferCaps::class)->checkReason((string) $offer->getKey(), null, 1000))
            ->toBe('site_prepaid_balance');
    });
});

describe('power feature: partial refund and multi-event CPA', function (): void {
    test('adjusting a leg posts an idempotent companion and updates counters', function (): void {
        $offer = powerOffer();
        $affiliate = powerAffiliate();
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create();
        $leg = powerPost($offer, $affiliate, 'ADJ-1', 100000, ['link_id' => (string) $link->getKey(), 'link_code' => $link->link->slug]);

        $adjustment = app(AdjustConversionLeg::class)->execute((string) $leg->getKey(), 'ADJ-REF-1', -50000, 'partial refund');

        expect($adjustment->revenue_minor)->toBe(-50000)
            ->and($adjustment->commission_minor)->toBe(-5000)
            ->and($adjustment->parent_leg_id)->toBe((string) $leg->getKey());

        $again = app(AdjustConversionLeg::class)->execute((string) $leg->getKey(), 'ADJ-REF-1', -50000, 'retry');

        expect($again->getKey())->toBe($adjustment->getKey())
            ->and(NetworkConversionLeg::query()->where('parent_leg_id', $leg->getKey())->count())->toBe(1);
    });

    test('distinct events on one reference earn separate multi-event CPA', function (): void {
        $offer = powerOffer([
            'rate_base_bp' => null,
            'event_rates' => [
                'purchase' => ['rate_fixed_minor' => 1000],
                'subscription' => ['rate_fixed_minor' => 250],
            ],
        ]);
        $affiliate = powerAffiliate();

        $purchase = powerPost($offer, $affiliate, 'MULTI-1', 0, ['event_type' => 'purchase']);
        $subscription = powerPost($offer, $affiliate, 'MULTI-1', 0, ['event_type' => 'subscription']);

        expect($purchase->getKey())->not->toBe($subscription->getKey())
            ->and($purchase->commission_minor)->toBe(1000)
            ->and($subscription->commission_minor)->toBe(250);
    });
});

describe('power feature: marketplace discovery and metrics', function (): void {
    test('discovery filters by category subtree, currency, rate and featured', function (): void {
        $parent = AffiliateOfferCategory::factory()->create();
        $child = AffiliateOfferCategory::factory()->forParent($parent)->create();

        $inCategory = powerOffer(['category_id' => $child->getKey(), 'is_featured' => true, 'rate_base_bp' => 2500, 'currency' => 'MYR']);
        $other = powerOffer(['rate_base_bp' => 500, 'currency' => 'USD']);

        $query = app(OfferDiscoveryQuery::class);

        $byCategory = array_map(fn ($offer): string => $offer->id, $query->execute(new OfferDiscoveryFilter(categoryId: (string) $parent->getKey())));
        $byCurrency = array_map(fn ($offer): string => $offer->id, $query->execute(new OfferDiscoveryFilter(currency: 'USD')));
        $byRate = array_map(fn ($offer): string => $offer->id, $query->execute(new OfferDiscoveryFilter(minRateBp: 2000)));
        $byFeatured = array_map(fn ($offer): string => $offer->id, $query->execute(new OfferDiscoveryFilter(featured: true)));

        expect($byCategory)->toContain($inCategory->getKey())
            ->and($byCurrency)->toContain($other->getKey())
            ->and($byRate)->toContain($inCategory->getKey())
            ->and($byFeatured)->toContain($inCategory->getKey());
    });

    test('discovery applies restriction filtering', function (): void {
        $offer = powerOffer(['restrictions' => ['blocked_countries' => ['US']]]);
        $query = app(OfferDiscoveryQuery::class);

        $allowed = array_map(fn ($o): string => $o->id, $query->execute(new OfferDiscoveryFilter(country: 'MY')));
        $blocked = array_map(fn ($o): string => $o->id, $query->execute(new OfferDiscoveryFilter(country: 'US')));

        expect($allowed)->toContain($offer->getKey())
            ->and($blocked)->not->toContain($offer->getKey());
    });

    test('offer metrics compute epc, conversion and reversal rates', function (): void {
        $offer = powerOffer();
        $affiliate = powerAffiliate();
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->withStats(10, 0, 0)->create();

        powerPost($offer, $affiliate, 'METRIC-1', 10000, ['link_id' => (string) $link->getKey(), 'link_code' => $link->link->slug]);
        $reversed = powerPost($offer, $affiliate, 'METRIC-2', 10000, ['link_id' => (string) $link->getKey(), 'link_code' => $link->link->slug]);
        app(ReverseConversionLeg::class)->execute((string) $reversed->getKey(), 'refund');

        $metrics = app(OfferMetricsQuery::class)->forOffer($offer->toRecord());

        expect($metrics->clicks)->toBe(10)
            ->and($metrics->conversions)->toBeGreaterThanOrEqual(1)
            ->and($metrics->reversalRate)->toBeGreaterThan(0.0)
            ->and($metrics->epcMinor)->toBeGreaterThan(0.0);
    });
});

describe('power feature: restrictions and promotions enforcement', function (): void {
    test('country restriction blocks posting a conversion', function (): void {
        $offer = powerOffer(['restrictions' => ['blocked_countries' => ['US']]]);

        expect(fn () => app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) powerAffiliate()->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'RESTRICT-1',
        ))->not->toThrow(RestrictionViolatedException::class);
    });

    test('an active bonus rate promotion lifts the commission', function (): void {
        $offer = powerOffer([
            'rate_base_bp' => 1000,
            'active_promotions' => [[
                'id' => 'bonus-1',
                'bonus_rate_bp' => 500,
                'starts_at' => CarbonImmutable::now()->subDay()->toIso8601String(),
                'ends_at' => CarbonImmutable::now()->addDay()->toIso8601String(),
            ]],
        ]);

        $leg = powerPost($offer, powerAffiliate(), 'PROMO-1', 10000);

        expect($leg->commission_minor)->toBe(1500)
            ->and($leg->metadata['applied_promotion_id'])->toBe('bonus-1');
    });

    test('the catalog restriction evaluator flags a channel violation', function (): void {
        $offer = powerOffer(['restrictions' => ['blocked_channels' => ['email']]]);

        $evaluator = app(EvaluateOfferRestrictions::class);

        expect($evaluator->evaluate($offer->toRecord(), new RestrictionContext(channel: 'email')))->toBe('channel_restricted')
            ->and($evaluator->evaluate($offer->toRecord(), new RestrictionContext(channel: 'social')))->toBeNull();
    });
});
