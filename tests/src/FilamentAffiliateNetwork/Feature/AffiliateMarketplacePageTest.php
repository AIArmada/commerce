<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage;

describe('AffiliateMarketplacePage', function (): void {
    beforeEach(function (): void {
        $this->user = User::create([
            'name' => 'Affiliate User',
            'email' => 'affiliate' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Marketplace Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => $this->user->email,
        ]);

        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'marketplace.example',
        ]);
    });

    test('applying for offer requiring approval creates an application', function (): void {
        $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'is_public' => true,
            'requires_approval' => true,
        ]);

        $this->actingAs($this->user);

        app(AffiliateMarketplacePage::class)->applyForOffer($offer->id, 'I have a strong audience');

        expect(AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeTrue();

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();
    });

    test('applying for offer without approval requirement generates a link instead of an application', function (): void {
        $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'is_public' => true,
            'requires_approval' => false,
            'landing_url' => 'https://marketplace.example/offers/direct',
        ]);

        $this->actingAs($this->user);

        app(AffiliateMarketplacePage::class)->applyForOffer($offer->id, 'unused reason');

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->count())->toBe(1);

        expect(AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();
    });

    test('disabled affiliates cannot apply for offers', function (): void {
        $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'is_public' => true,
            'requires_approval' => true,
        ]);

        $this->affiliate->update([
            'status' => Disabled::class,
        ]);

        $this->actingAs($this->user);

        app(AffiliateMarketplacePage::class)->applyForOffer($offer->id, 'I should not be able to apply');

        expect(AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();
    });

    test('unapproved affiliate cannot call generateLink directly for offer requiring approval', function (): void {
        $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'is_public' => true,
            'requires_approval' => true,
        ]);

        $this->actingAs($this->user);

        app(AffiliateMarketplacePage::class)->generateLink($offer->id);

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();
    });

    test('approved affiliate can call generateLink for offer requiring approval', function (): void {
        $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'is_public' => true,
            'requires_approval' => true,
            'landing_url' => 'https://marketplace.example/approved',
        ]);

        AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => AffiliateOfferApplication::STATUS_APPROVED,
        ]);

        $this->actingAs($this->user);

        app(AffiliateMarketplacePage::class)->generateLink($offer->id);

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeTrue();
    });

    test('owner-enabled marketplace applies for tenant-owned offer using affiliate owner context', function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);

        $owner = User::factory()->create();
        $ownedUser = User::create([
            'name' => 'Tenant Affiliate User',
            'email' => 'tenant-affiliate-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $site = OwnerContext::withOwner($owner, fn () => AffiliateSite::factory()->verified()->forOwner($owner)->create([
            'domain' => 'tenant-marketplace.example',
        ]));

        $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->active()->forSite($site)->create([
            'is_public' => true,
            'requires_approval' => true,
        ]));

        $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Tenant Marketplace Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => $ownedUser->email,
        ]));

        $this->actingAs($ownedUser);

        app(AffiliateMarketplacePage::class)->applyForOffer($offer->id, 'I know this tenant audience well.');

        $applicationExists = OwnerContext::withOwner($owner, fn (): bool => AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->exists());

        expect($applicationExists)->toBeTrue();
    });

    test('owner-enabled marketplace generates links for approved tenant-owned affiliate offers', function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);

        $owner = User::factory()->create();
        $ownedUser = User::create([
            'name' => 'Tenant Link User',
            'email' => 'tenant-links-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $site = OwnerContext::withOwner($owner, fn () => AffiliateSite::factory()->verified()->forOwner($owner)->create([
            'domain' => 'tenant-links.example',
        ]));

        $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->active()->forSite($site)->create([
            'is_public' => true,
            'requires_approval' => true,
            'landing_url' => 'https://tenant-links.example/approved',
        ]));

        $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Tenant Link Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => $ownedUser->email,
        ]));

        OwnerContext::withOwner($owner, fn () => AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'status' => AffiliateOfferApplication::STATUS_APPROVED,
        ]));

        $this->actingAs($ownedUser);

        app(AffiliateMarketplacePage::class)->generateLink($offer->id);

        $linkCount = OwnerContext::withOwner($owner, fn (): int => AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->count());

        expect($linkCount)->toBe(1);
    });

    test('owner-enabled marketplace categories include tenant-owned categories in public view', function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);

        $owner = User::factory()->create();

        $category = OwnerContext::withOwner($owner, fn () => AffiliateOfferCategory::factory()->forOwner($owner)->create([
            'name' => 'Tenant Category',
            'is_active' => true,
        ]));

        $categories = app(AffiliateMarketplacePage::class)->getCategories();

        expect($categories->pluck('id'))
            ->toContain($category->id);
    });
});
