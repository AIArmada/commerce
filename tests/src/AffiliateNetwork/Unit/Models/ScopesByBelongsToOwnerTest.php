<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

describe('ScopesByBelongsToOwner', function (): void {
    test('registers the belongs-to owner scope on offer models', function (): void {
        expect(array_key_exists(ScopesByBelongsToOwner::class, (new AffiliateOffer)->getGlobalScopes()))->toBeTrue()
            ->and(array_key_exists(ScopesByBelongsToOwner::class, (new AffiliateOfferCreative)->getGlobalScopes()))->toBeTrue();
    });

    test('keeps direct, site, affiliate, and dotted owner paths in parity', function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliate-network.owner.include_global' => false,
            'affiliates.owner.enabled' => true,
            'affiliates.owner.include_global' => false,
        ]);

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $records = [];

        foreach ([['owner' => $ownerA, 'suffix' => 'a'], ['owner' => $ownerB, 'suffix' => 'b']] as $entry) {
            $records[$entry['suffix']] = OwnerContext::withOwner($entry['owner'], function () use ($entry): array {
                $site = AffiliateSite::factory()->verified()->forOwner($entry['owner'])->create([
                    'domain' => "{$entry['suffix']}.example.com",
                ]);
                $category = AffiliateOfferCategory::factory()->forOwner($entry['owner'])->create();
                $offer = AffiliateOffer::factory()->published()->forSite($site)->forCategory($category)->create();
                $creative = AffiliateOfferCreative::factory()->forOffer($offer)->create();
                $affiliate = Affiliate::create([
                    'code' => 'AFF-' . $entry['suffix'] . '-' . uniqid(),
                    'name' => 'Affiliate ' . mb_strtoupper($entry['suffix']),
                    'status' => 'active',
                    'commission_type' => 'percentage',
                    'commission_rate' => 1000,
                    'currency' => 'USD',
                ]);
                $application = AffiliateOfferApplication::create([
                    'offer_id' => $offer->id,
                    'affiliate_id' => $affiliate->id,
                    'status' => 'pending',
                ]);
                $link = AffiliateOfferLink::create([
                    'offer_id' => $offer->id,
                    'affiliate_id' => $affiliate->id,
                    'site_id' => $site->id,
                ]);

                return compact('site', 'category', 'offer', 'creative', 'affiliate', 'application', 'link');
            });
        }

        $ownerAIds = OwnerContext::withOwner($ownerA, fn (): array => [
            AffiliateSite::query()->pluck('id')->all(),
            AffiliateOfferCategory::query()->pluck('id')->all(),
            AffiliateOffer::query()->pluck('id')->all(),
            AffiliateOfferCreative::query()->pluck('id')->all(),
            AffiliateOfferApplication::query()->pluck('id')->all(),
            AffiliateOfferLink::query()->pluck('id')->all(),
        ]);

        expect($ownerAIds)->toBe([
            [$records['a']['site']->id],
            [$records['a']['category']->id],
            [$records['a']['offer']->id],
            [$records['a']['creative']->id],
            [$records['a']['application']->id],
            [$records['a']['link']->id],
        ]);

        $globalIds = OwnerContext::withOwner(null, fn (): array => [
            AffiliateSite::query()->pluck('id')->all(),
            AffiliateOfferCategory::query()->pluck('id')->all(),
            AffiliateOffer::query()->pluck('id')->all(),
            AffiliateOfferCreative::query()->pluck('id')->all(),
            AffiliateOfferApplication::query()->pluck('id')->all(),
            AffiliateOfferLink::query()->pluck('id')->all(),
        ]);

        expect($globalIds)->toBe([[], [], [], [], [], []]);
    });
});

test('rejects reparenting and deleting a foreign record through an unsaved owner-key change', function (): void {
    config(['affiliate-network.owner.enabled' => true, 'affiliate-network.owner.include_global' => false]);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$foreignSite, $foreignOffer] = OwnerContext::withOwner($ownerA, function () use ($ownerA): array {
        $site = AffiliateSite::factory()->verified()->forOwner($ownerA)->create();

        return [$site, AffiliateOffer::factory()->published()->forSite($site)->create()];
    });
    $ownSite = OwnerContext::withOwner($ownerB, fn (): AffiliateSite => AffiliateSite::factory()->verified()->forOwner($ownerB)->create());

    OwnerContext::withOwner($ownerB, function () use ($foreignOffer, $ownSite): void {
        $attempt = clone $foreignOffer;
        $attempt->site_id = $ownSite->id;

        expect(fn () => $attempt->save())->toThrow(RuntimeException::class)
            ->and(fn () => $attempt->delete())->toThrow(RuntimeException::class);
    });

    OwnerContext::withOwner($ownerA, function () use ($foreignSite, $foreignOffer): void {
        expect(AffiliateOffer::query()->findOrFail($foreignOffer->id)->site_id)->toBe($foreignSite->id);
    });
});
