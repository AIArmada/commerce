<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

describe('ScopesByBelongsToOwner', function (): void {
    test('replaces the two retired relationship scope traits', function (): void {
        expect(class_exists('AIArmada\\AffiliateNetwork\\Models\\Concerns\\ScopesByAffiliateOwner'))->toBeFalse()
            ->and(class_exists('AIArmada\\AffiliateNetwork\\Models\\Concerns\\ScopesBySiteOwner'))->toBeFalse()
            ->and(array_key_exists(ScopesByBelongsToOwner::class, (new AffiliateOffer)->getGlobalScopes()))->toBeTrue()
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
                    'target_url' => 'https://example.com/' . $entry['suffix'],
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
