<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ConfirmConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\SupersedeConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\LedgerReconciliationQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Affiliates\States\RejectedConversion;
use Illuminate\Support\Facades\Event;

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

function postBooksLeg(AffiliateOfferLink $link, int $revenueMinor, string $currency, string $reference, LegStatus $status = LegStatus::Posted): NetworkConversionLeg
{
    return app(PostConversionLeg::class)->execute(
        offerId: (string) $link->offer_id,
        affiliateId: (string) $link->affiliate_id,
        revenueMinor: $revenueMinor,
        currency: $currency,
        externalReference: $reference,
        status: $status,
        linkId: (string) $link->getKey(),
    );
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

describe('ledger leg actions', function (): void {
    test('posting is idempotent on link and reference', function (): void {
        $link = booksFixtures();

        $first = postBooksLeg($link, 89900, 'MYR', 'BOOKS-1');
        $second = postBooksLeg($link, 89900, 'MYR', 'BOOKS-1');

        expect($second->getKey())->toBe($first->getKey())
            ->and(NetworkConversionLeg::query()->count())->toBe(1);
    });

    test('a provisional leg cannot be reversed but a posted one can', function (): void {
        Event::fake([NetworkConversionRecorded::class]);

        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-INITIAL', LegStatus::Provisional);

        expect(fn () => app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'refund'))
            ->toThrow(InvalidArgumentException::class);

        expect(NetworkConversionLeg::query()->count())->toBe(1)
            ->and(AffiliateConversion::query()->count())->toBe(0)
            ->and($link->fresh()->conversions)->toBe(0)
            ->and($link->fresh()->revenue)->toBe(0);

        Event::assertNotDispatched(NetworkConversionRecorded::class);
    });

    test('an idempotent replay returns its existing leg before reversed status validation', function (): void {
        $link = booksFixtures();

        $first = postBooksLeg($link, 10000, 'MYR', 'BOOKS-REVERSED-REPLAY');
        $replay = postBooksLeg($link, 10000, 'MYR', 'BOOKS-REVERSED-REPLAY', LegStatus::Reversed);

        expect($first)->toBeInstanceOf(NetworkConversionLeg::class)
            ->and($replay?->getKey())->toBe($first?->getKey())
            ->and($replay?->status)->toBe(LegStatus::Posted)
            ->and($replay?->wasRecentlyCreated)->toBeFalse()
            ->and(NetworkConversionLeg::query()->count())->toBe(1);
    });

    test('a missing offer yields zero commission in the requested currency', function (): void {
        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-MISSING-OFFER');

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) Str::uuid(),
            affiliateId: (string) $link->affiliate_id,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'BOOKS-MISSING-OFFER-2',
            linkId: (string) $link->getKey(),
        );

        expect($leg->commission_minor)->toBe(0)
            ->and($leg->commission_currency)->toBe('MYR');
    });

    test('fixed rate wins over tiers and base', function (): void {
        $link = booksFixtures([
            'rate_fixed_minor' => 2500,
            'volume_tiers' => [['min_volume_minor' => 0, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $leg = postBooksLeg($link, 99999, 'MYR', 'BOOKS-FIXED');

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

        $first = postBooksLeg($link, 60000, 'MYR', 'BOOKS-T1');
        $second = postBooksLeg($link, 60000, 'MYR', 'BOOKS-T2');

        expect($first->commission_minor)->toBe(6000)
            ->and($first->tier_rate_bp)->toBe(1000)
            ->and($second->commission_minor)->toBe(12000)
            ->and($second->tier_rate_bp)->toBe(2000)
            ->and($second->tier_min_volume_minor)->toBe(100000);
    });

    test('currency mismatch does not earn a commission or inherit a tier', function (): void {
        $link = booksFixtures([
            'volume_tiers' => [['min_volume_minor' => 100000, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $other = AffiliateOfferLink::factory()
            ->forOffer($link->offer)
            ->forAffiliateId('books-aff-2')
            ->create();

        postBooksLeg($other, 500000, 'MYR', 'BOOKS-TX');

        $leg = postBooksLeg($link, 60000, 'USD', 'BOOKS-T3');

        expect($leg->commission_minor)->toBe(0)
            ->and($leg->tier_rate_bp)->toBeNull();
    });

    test('network fee splits commission into payout and fee', function (): void {
        $link = booksFixtures(['rate_base_bp' => 1000, 'network_fee_bp' => 2000]);

        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-FEE');

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->fee_minor)->toBe(200)
            ->and($leg->fee_bp)->toBe(2000)
            ->and($leg->payout_minor)->toBe(800);
    });

    test('fee defaults from config and clamps to commission', function (): void {
        config(['affiliate-network.fees.default_bp' => 10000]);
        $link = booksFixtures(['rate_base_bp' => 1000]);

        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-FEEMAX');

        expect($leg->fee_minor)->toBe(1000)
            ->and($leg->payout_minor)->toBe(0);
    });

    test('legs preserve revenue but post zero commission on currency mismatch', function (): void {
        $link = booksFixtures();
        $link->offer->update(['currency' => 'USD']);

        $leg = postBooksLeg($link, 12345, 'MYR', 'BOOKS-FX');

        expect($leg->revenue_minor)->toBe(12345)
            ->and($leg->revenue_currency)->toBe('MYR')
            ->and($leg->commission_minor)->toBe(0)
            ->and($leg->fee_minor)->toBe(0)
            ->and($leg->payout_minor)->toBe(0);
    });

    test('confirm and supersede finalize provisional legs once', function (): void {
        $link = booksFixtures();

        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-PROV', LegStatus::Provisional);
        $confirmed = app(ConfirmConversionLeg::class)->execute($leg);
        $again = app(ConfirmConversionLeg::class)->execute($confirmed);

        expect($confirmed->status)->toBe(LegStatus::Posted)
            ->and($again->getKey())->toBe($confirmed->getKey());

        $loser = postBooksLeg($link, 10000, 'MYR', 'BOOKS-LOSE', LegStatus::Provisional);
        $superseded = app(SupersedeConversionLeg::class)->execute($loser, 'engine_won');

        expect($superseded->status)->toBe(LegStatus::Superseded)
            ->and($superseded->metadata['superseded_reason'])->toBe('engine_won');
    });

    test('reverse marks the leg and posts a negated companion', function (): void {
        $link = booksFixtures(['network_fee_bp' => 2000]);
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-REV');

        $companion = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');

        expect($companion->commission_minor)->toBe(-1000)
            ->and($companion->fee_minor)->toBe(-200)
            ->and($companion->payout_minor)->toBe(-800)
            ->and($companion->status)->toBe(LegStatus::Reversed)
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed);
    });

    test('duplicate reversals with different reasons share one companion', function (): void {
        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-DUPREV');

        $first = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');
        $second = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'duplicate');

        expect($second->getKey())->toBe($first->getKey())
            ->and(NetworkConversionLeg::query()->where('metadata->reverses_id', (string) $leg->getKey())->count())->toBe(1);
    });

    test('reversal companions stay within the reference column for long originals', function (): void {
        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', str_repeat('R', 120));

        $companion = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');

        expect(mb_strlen($companion->external_reference))->toBeLessThanOrEqual(120)
            ->and($companion->commission_minor)->toBe(-1000)
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed);
    });

    test('same-prefix legs under one link reverse to distinct companions', function (): void {
        $link = booksFixtures();
        $prefix = str_repeat('R', 100);

        $legA = postBooksLeg($link, 10000, 'MYR', $prefix . str_repeat('A', 20));
        $legB = postBooksLeg($link, 10000, 'MYR', $prefix . str_repeat('B', 20));

        $companionA = app(ReverseConversionLeg::class)->execute((string) $legA->getKey(), 'chargeback');
        $companionB = app(ReverseConversionLeg::class)->execute((string) $legB->getKey(), 'chargeback');

        expect($companionA->external_reference)->not->toBe($companionB->external_reference)
            ->and(mb_strlen($companionA->external_reference))->toBeLessThanOrEqual(120)
            ->and(mb_strlen($companionB->external_reference))->toBeLessThanOrEqual(120);
    });

    test('duplicate reversal returns its companion without voiding the merchant posting twice', function (): void {
        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-REVOID');

        $fake = new RecordingNetworkLedgerFake;
        app()->instance(NetworkLedger::class, $fake);

        $first = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');
        $second = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');

        expect($second->getKey())->toBe($first->getKey())
            ->and($fake->voided)->toBe([[(string) $link->getKey(), 'BOOKS-REVOID', 'chargeback']]);
    });

    test('reversing a companion is an idempotent no-op', function (): void {
        $link = booksFixtures();
        $leg = postBooksLeg($link, 10000, 'MYR', 'BOOKS-RECOMP');

        $companion = app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'chargeback');
        $legsBefore = NetworkConversionLeg::query()->count();

        $fake = new RecordingNetworkLedgerFake;
        app()->instance(NetworkLedger::class, $fake);

        $result = app(ReverseConversionLeg::class)->execute((string) $companion->getKey(), 'chargeback');

        expect($result->getKey())->toBe($companion->getKey())
            ->and($result->commission_minor)->toBe(-1000)
            ->and(NetworkConversionLeg::query()->count())->toBe($legsBefore)
            ->and($fake->voided)->toBeEmpty();
    });

    test('recount rebuilds counters from legs', function (): void {
        $link = booksFixtures();
        postBooksLeg($link, 10000, 'MYR', 'BOOKS-RC1');
        postBooksLeg($link, 5000, 'MYR', 'BOOKS-RC2');

        $link->forceFill(['conversions' => 99, 'revenue' => 1])->save();

        $recount = app(LedgerReconciliationQuery::class)->computeCounters((string) $link->getKey());

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

    $leg = app(RecordNetworkConversion::class)->execute(
        offerId: (string) $offer->getKey(),
        affiliateId: (string) $affiliate->getKey(),
        revenueMinor: 89900,
        currency: 'MYR',
        externalReference: 'VOID-E2E-1',
        linkId: (string) $link->getKey(),
    );

    expect(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'MYR')->first()->holding_minor)->toBeGreaterThan(0);

    app(ReverseConversionLeg::class)->execute((string) $leg->getKey(), 'refund');

    expect($leg->fresh()->status)->toBe(LegStatus::Reversed);

    $conversion = AffiliateConversion::query()->where('external_reference', 'VOID-E2E-1')->firstOrFail();
    $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'MYR')->first()->fresh();

    expect($conversion->status->equals(RejectedConversion::class))->toBeTrue()
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});
