<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Affiliates\States\RejectedConversion;

function booksFixtures(array $offerAttributes = []): AffiliateOfferLink
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'books-' . uniqid() . '.example']);

    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'landing_url' => 'https://books.example/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ], $offerAttributes));

    return AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('books-aff-1')->create();
}

final class RecordingNetworkLedgerFake implements NetworkLedger
{
    /** @var list<array{string, string, string}> */
    public array $voided = [];

    public function post(NetworkConversionDraft $draft): ?NetworkPostedConversion
    {
        return null;
    }

    public function findPosted(string $linkId, string $externalReference): ?NetworkPostedConversion
    {
        return null;
    }

    public function voidPosting(string $linkId, string $externalReference, string $reason): ?NetworkPostedConversion
    {
        $this->voided[] = [$linkId, $externalReference, $reason];

        return null;
    }

    public function rowsForLink(string $linkId): array
    {
        return [];
    }

    public function postingsForExternalReference(string $externalReference): array
    {
        return [];
    }
}

describe('NetworkBooks', function (): void {
    test('posting is idempotent on link and reference', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);

        $first = $books->post($link, 89900, 'MYR', 'BOOKS-1');
        $second = $books->post($link, 89900, 'MYR', 'BOOKS-1');

        expect($second->getKey())->toBe($first->getKey())
            ->and(NetworkConversionLeg::query()->count())->toBe(1);
    });

    test('fixed rate wins over tiers and base', function (): void {
        $link = booksFixtures([
            'rate_fixed_minor' => 2500,
            'volume_tiers' => [['min_volume_minor' => 0, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $leg = app(NetworkBooks::class)->post($link, 99999, 'MYR', 'BOOKS-FIXED');

        expect($leg->commission_minor)->toBe(2500)
            ->and($leg->tier_rate_bp)->toBeNull();
    });

    test('volume tiers apply on cumulative affiliate revenue', function (): void {
        $link = booksFixtures([
            'rate_base_bp' => 1000,
            'volume_tiers' => [
                ['min_volume_minor' => 0, 'rate_bp' => 1000, 'currency' => 'MYR'],
                ['min_volume_minor' => 100000, 'rate_bp' => 2000, 'currency' => 'MYR'],
            ],
        ]);
        $books = app(NetworkBooks::class);

        $first = $books->post($link, 60000, 'MYR', 'BOOKS-T1');
        $second = $books->post($link, 60000, 'MYR', 'BOOKS-T2');

        expect($first->commission_minor)->toBe(6000)
            ->and($first->tier_rate_bp)->toBe(1000)
            ->and($second->commission_minor)->toBe(12000)
            ->and($second->tier_rate_bp)->toBe(2000)
            ->and($second->tier_min_volume_minor)->toBe(100000);
    });

    test('tiers ignore other affiliates and currencies', function (): void {
        $link = booksFixtures([
            'volume_tiers' => [['min_volume_minor' => 100000, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $other = AffiliateOfferLink::factory()
            ->forOffer($link->offer)
            ->forAffiliateId('books-aff-2')
            ->create();

        app(NetworkBooks::class)->post($other, 500000, 'MYR', 'BOOKS-TX');

        $leg = app(NetworkBooks::class)->post($link, 60000, 'USD', 'BOOKS-T3');

        expect($leg->commission_minor)->toBe(6000)
            ->and($leg->tier_rate_bp)->toBeNull();
    });

    test('network fee splits commission into payout and fee', function (): void {
        $link = booksFixtures(['rate_base_bp' => 1000, 'network_fee_bp' => 2000]);

        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'BOOKS-FEE');

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->fee_minor)->toBe(200)
            ->and($leg->fee_bp)->toBe(2000)
            ->and($leg->payout_minor)->toBe(800);
    });

    test('fee defaults from config and clamps to commission', function (): void {
        config(['affiliate-network.fees.default_bp' => 10000]);
        $link = booksFixtures(['rate_base_bp' => 1000]);

        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'BOOKS-FEEMAX');

        expect($leg->fee_minor)->toBe(1000)
            ->and($leg->payout_minor)->toBe(0);
    });

    test('legs keep real money on currency mismatch', function (): void {
        $link = booksFixtures();
        $link->update(['currency' => 'USD']);

        $leg = app(NetworkBooks::class)->post($link, 12345, 'MYR', 'BOOKS-FX');

        expect($leg->revenue_minor)->toBe(12345)
            ->and($leg->revenue_currency)->toBe('MYR');
    });

    test('confirm and supersede finalize provisional legs once', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);

        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-PROV', LegStatus::Provisional);
        $confirmed = $books->confirm($leg);
        $again = $books->confirm($confirmed);

        expect($confirmed->status)->toBe(LegStatus::Posted)
            ->and($again->getKey())->toBe($confirmed->getKey());

        $loser = $books->post($link, 10000, 'MYR', 'BOOKS-LOSE', LegStatus::Provisional);
        $superseded = $books->supersede($loser, 'engine_won');

        expect($superseded->status)->toBe(LegStatus::Superseded)
            ->and($superseded->metadata['superseded_reason'])->toBe('engine_won');
    });

    test('reverse marks the leg and posts a negated companion', function (): void {
        $link = booksFixtures(['network_fee_bp' => 2000]);
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-REV');

        $companion = $books->reverse($leg, 'chargeback');

        expect($companion->commission_minor)->toBe(-1000)
            ->and($companion->fee_minor)->toBe(-200)
            ->and($companion->payout_minor)->toBe(-800)
            ->and($companion->status)->toBe(LegStatus::Reversed)
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed);
    });

    test('duplicate reversals with different reasons share one companion', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-DUPREV');

        $first = $books->reverse($leg, 'chargeback');
        // Deliberately stale: simulates a concurrent reversal that read
        // the leg before the first one committed.
        $second = $books->reverse($leg, 'duplicate');

        expect($second->getKey())->toBe($first->getKey())
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1);
    });

    test('reversal companions stay within the reference column for long originals', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', str_repeat('R', 120));

        $companion = $books->reverse($leg, 'chargeback');

        // The column caps at 120: an unbounded ':reversal' suffix would
        // make this leg permanently un-reversible on MySQL/Postgres.
        expect(mb_strlen($companion->external_reference))->toBeLessThanOrEqual(120)
            ->and($companion->commission_minor)->toBe(-1000)
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed);
    });

    test('same-prefix legs under one link reverse to distinct companions', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $prefix = str_repeat('R', 100);

        $legA = $books->post($link, 10000, 'MYR', $prefix . str_repeat('A', 20));
        $legB = $books->post($link, 10000, 'MYR', $prefix . str_repeat('B', 20));

        $companionA = $books->reverse($legA, 'chargeback');
        $companionB = $books->reverse($legB, 'chargeback');

        expect($companionA->external_reference)->not->toBe($companionB->external_reference)
            ->and(mb_strlen($companionA->external_reference))->toBeLessThanOrEqual(120)
            ->and(mb_strlen($companionB->external_reference))->toBeLessThanOrEqual(120);
    });

    test('reverse repairs a reversed leg whose companion is missing', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-NOCOMP');

        // Legacy damage: reversed without a companion (a previous
        // non-transactional implementation could leave this behind).
        $leg->forceFill(['status' => LegStatus::Reversed])->save();

        $companion = $books->reverse($leg->fresh(), 'chargeback');

        expect($companion->getKey())->not->toBe($leg->getKey())
            ->and($companion->commission_minor)->toBe(-1000)
            ->and($companion->status)->toBe(LegStatus::Reversed)
            ->and($companion->metadata['reverses_id'])->toBe((string) $leg->getKey())
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1);
    });

    test('reversing a reversed leg with a companion still voids the merchant posting', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-REVOID');

        // First reversal creates the companion (the real ledger finds no
        // merchant posting for this fixture link, so nothing is voided).
        $first = $books->reverse($leg, 'chargeback');

        $fake = new RecordingNetworkLedgerFake;
        app()->instance(NetworkLedger::class, $fake);

        $second = $books->reverse($leg->fresh(), 'chargeback');

        expect($second->getKey())->toBe($first->getKey())
            ->and($fake->voided)->toBe([[(string) $link->getKey(), 'BOOKS-REVOID', 'chargeback']]);
    });

    test('reversing a companion is an idempotent no-op', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-RECOMP');

        $companion = $books->reverse($leg, 'chargeback');
        $legsBefore = NetworkConversionLeg::query()->count();

        $fake = new RecordingNetworkLedgerFake;
        app()->instance(NetworkLedger::class, $fake);

        $result = $books->reverse($companion->fresh(), 'chargeback');

        // The companion IS the reversal record: re-reversing it must not
        // mint a second-order leg with positive amounts, and must not
        // re-void (the original's void was handled at creation).
        expect($result->getKey())->toBe($companion->getKey())
            ->and($result->commission_minor)->toBe(-1000)
            ->and(NetworkConversionLeg::query()->count())->toBe($legsBefore)
            ->and($fake->voided)->toBeEmpty();
    });

    test('recount rebuilds counters from legs', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $books->post($link, 10000, 'MYR', 'BOOKS-RC1');
        $books->post($link, 5000, 'MYR', 'BOOKS-RC2');

        $link->forceFill(['conversions' => 99, 'revenue' => 1])->save();

        $recount = $books->recountLinkCounters($link->fresh());

        expect($recount)->toBe(['conversions' => 2, 'revenue_minor' => 15000])
            ->and($link->fresh()->conversions)->toBe(2)
            ->and($link->fresh()->revenue)->toBe(15000);
    });
});

test('reversing a leg voids the merchant posting with balanced money', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'VOIDLEG-' . uniqid(),
        'name' => 'Leg Void Affiliate',
        'status' => AffiliateActive::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $site = AffiliateSite::factory()->verified()->create(['domain' => 'voidleg-' . uniqid() . '.example']);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => 'https://voidleg.example/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);
    $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create();

    $leg = app(OfferLinkService::class)->recordConversion($link, 89900, 'MYR', 'VOID-E2E-1');

    expect($leg)->not->toBeNull()
        ->and($affiliate->balanceFor('MYR')->holding_minor)->toBeGreaterThan(0);

    app(NetworkBooks::class)->reverse($leg, 'refund');

    expect($leg->fresh()->status)->toBe(LegStatus::Reversed);

    $conversion = AffiliateConversion::query()->where('external_reference', 'VOID-E2E-1')->firstOrFail();
    $balance = $affiliate->balanceFor('MYR')->fresh();

    expect($conversion->status->equals(RejectedConversion::class))->toBeTrue()
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});
