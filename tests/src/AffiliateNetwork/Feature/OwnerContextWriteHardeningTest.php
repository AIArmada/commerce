<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ConfirmConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\FulfillConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\SupersedeConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\AdjustMerchantPrepaidBalance;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;

/**
 * @return array{0: User, 1: User, 2: AffiliateSite, 3: Affiliate}
 */
function writeOwnerFixtures(string $suffix): array
{
    $merchant = User::factory()->create();
    $creator = User::factory()->create();

    [$site, $affiliate] = OwnerContext::withOwner($merchant, function () use ($merchant, $creator, $suffix): array {
        $site = AffiliateSite::factory()->verified()->forOwner($merchant)->create([
            'domain' => 'write-' . $suffix . '.example',
            'prepaid_balance_minor' => 100000,
        ]);

        $affiliate = OwnerContext::withOwner($creator, fn (): Affiliate => Affiliate::forceCreate([
            'code' => 'WO' . mb_strtoupper($suffix) . uniqid(),
            'handle' => 'wo-' . $suffix . '-' . uniqid(),
            'name' => 'Write Owner Affiliate ' . $suffix,
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
            'owner_type' => $creator->getMorphClass(),
            'owner_id' => (string) $creator->getKey(),
        ]));

        return [$site, $affiliate];
    });

    return [$merchant, $creator, $site, $affiliate];
}

function writeOwnerOffer(User $merchant, AffiliateSite $site, array $attributes = []): AffiliateOffer
{
    return OwnerContext::withOwner($merchant, fn (): AffiliateOffer => AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'rate_base_bp' => 1000,
        'currency' => 'MYR',
        'requires_approval' => false,
        'locking_days' => 0,
    ], $attributes)));
}

function writeOwnerLeg(AffiliateOffer $offer, Affiliate $affiliate, string $reference, array $extra = []): NetworkConversionLeg
{
    return app(PostConversionLeg::class)->execute(
        offerId: (string) $offer->getKey(),
        affiliateId: (string) $affiliate->getKey(),
        revenueMinor: 10000,
        currency: 'MYR',
        externalReference: $reference,
        linkId: $extra['link_id'] ?? null,
        linkCode: $extra['link_code'] ?? null,
        status: $extra['status'] ?? LegStatus::Posted,
    );
}

describe('owner-context write hardening', function (): void {
    beforeEach(function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliate-network.owner.include_global' => false,
            'affiliates.owner.enabled' => true,
            'affiliates.owner.include_global' => false,
        ]);
    });

    test('adjusting a leg succeeds under the creator owner while the ambient owner differs', function (): void {
        [$merchant, $creator, $site, $affiliate] = writeOwnerFixtures('adjust');
        $offer = writeOwnerOffer($merchant, $site);
        $link = OwnerContext::withOwner($creator, fn () => AffiliateOfferLink::factory()
            ->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create(['currency' => 'MYR']));
        $leg = OwnerContext::withOwner($creator, fn () => writeOwnerLeg($offer, $affiliate, 'WH-ADJ', ['link_id' => (string) $link->getKey(), 'link_code' => $link->link->slug]));

        $adjustment = OwnerContext::withOwner(User::factory()->create(), fn () => app(AdjustConversionLeg::class)
            ->execute((string) $leg->getKey(), 'WH-ADJ-REF', -2000, 'refund'));

        expect($adjustment->parent_leg_id)->toBe((string) $leg->getKey())
            ->and($adjustment->revenue_minor)->toBe(-2000);
    });

    test('locking a matured leg succeeds under the creator owner', function (): void {
        [$merchant, $creator, $site, $affiliate] = writeOwnerFixtures('lock');
        $offer = writeOwnerOffer($merchant, $site, ['locking_days' => 2]);
        $leg = OwnerContext::withOwner($creator, fn () => writeOwnerLeg($offer, $affiliate, 'WH-LOCK', ['status' => LegStatus::Posted]));
        OwnerContext::withOwner($creator, fn () => $leg->forceFill(['status' => LegStatus::Pending, 'locks_at' => CarbonImmutable::now()->subDay()])->save());

        $locked = OwnerContext::withOwner(User::factory()->create(), fn () => app(LockMaturedLegs::class)
            ->execute(CarbonImmutable::now()));

        expect($locked)->toContain((string) $leg->getKey())
            ->and($leg->fresh()->status)->toBe(LegStatus::Posted);
    });

    test('confirming a provisional leg succeeds under the creator owner', function (): void {
        [$merchant, $creator, $site, $affiliate] = writeOwnerFixtures('confirm');
        $offer = writeOwnerOffer($merchant, $site);
        $leg = OwnerContext::withOwner($creator, fn () => writeOwnerLeg($offer, $affiliate, 'WH-CONFIRM', ['status' => LegStatus::Provisional]));

        $confirmed = OwnerContext::withOwner(User::factory()->create(), fn () => app(ConfirmConversionLeg::class)->execute($leg));

        expect($confirmed->status)->toBe(LegStatus::Posted);
    });

    test('superseding a provisional leg succeeds under the creator owner', function (): void {
        [$merchant, $creator, $site, $affiliate] = writeOwnerFixtures('supersede');
        $offer = writeOwnerOffer($merchant, $site);
        $leg = OwnerContext::withOwner($creator, fn () => writeOwnerLeg($offer, $affiliate, 'WH-SUPER', ['status' => LegStatus::Provisional]));

        $superseded = OwnerContext::withOwner(User::factory()->create(), fn () => app(SupersedeConversionLeg::class)
            ->execute($leg, 'engine_won'));

        expect($superseded->status)->toBe(LegStatus::Superseded);
    });

    test('fulfilling a posted leg succeeds under the creator owner', function (): void {
        [$merchant, $creator, $site, $affiliate] = writeOwnerFixtures('fulfill');
        $offer = writeOwnerOffer($merchant, $site);
        $leg = OwnerContext::withOwner($creator, fn () => writeOwnerLeg($offer, $affiliate, 'WH-FULFILL'));

        $fulfilled = OwnerContext::withOwner(User::factory()->create(), fn () => app(FulfillConversionLeg::class)
            ->execute((string) $leg->getKey(), 'REF-1'));

        expect($fulfilled->fulfilled_at)->not->toBeNull();
    });

    test('adjusting a merchant prepaid balance succeeds under the site owner', function (): void {
        [$merchant, , $site] = writeOwnerFixtures('prepaid');

        $balance = OwnerContext::withOwner(User::factory()->create(), fn (): int => app(AdjustMerchantPrepaidBalance::class)
            ->execute((string) $site->getKey(), -1000));

        expect($balance)->toBe(99000)
            ->and((int) $site->fresh()->prepaid_balance_minor)->toBe(99000);
    });
});
