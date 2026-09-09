<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\States\Active;
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
            'status' => Active::class,
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
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
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
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
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
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
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
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
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
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
            'landing_url' => 'https://marketplace.example/approved',
        ]);

        AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => ApplicationStatus::Approved,
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

        $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->published()->forSite($site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
        ]));

        $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Tenant Marketplace Affiliate',
            'status' => Active::class,
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

        $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->published()->forSite($site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
            'landing_url' => 'https://tenant-links.example/approved',
        ]));

        $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Tenant Link Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => $ownedUser->email,
        ]));

        OwnerContext::withOwner($owner, fn () => AffiliateOfferApplication::create([
            'offer_id' => $offer->id,
            'affiliate_id' => $affiliate->id,
            'status' => ApplicationStatus::Approved,
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

    test('local imported offer enrolls in its existing core program without a network application', function (): void {
        config(['affiliates.owner.enabled' => false]);

        $program = AffiliateProgram::create([
            'name' => 'Core Marketplace Program',
            'slug' => 'core-marketplace-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'visibility' => ProgramVisibility::Public,
            'requires_approval' => false,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate_basis_points' => 1000,
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
            'external_program_id' => $program->getKey(),
            'metadata' => ['catalog_source' => 'local'],
        ]);

        $this->actingAs($this->user);
        $page = app(AffiliateMarketplacePage::class);

        $page->applyForOffer($offer->id, 'Use the existing core enrollment.');
        $page->applyForOffer($offer->id, 'Repeat enrollment is idempotent.');

        expect(AffiliateProgramMembership::query()
            ->where('program_id', $program->getKey())
            ->where('affiliate_id', $this->affiliate->getKey())
            ->count())->toBe(1)
            ->and(AffiliateOfferApplication::query()
                ->where('offer_id', $offer->getKey())
                ->where('affiliate_id', $this->affiliate->getKey())
                ->exists())->toBeFalse()
            ->and(AffiliateProgramMembership::query()
                ->where('program_id', $program->getKey())
                ->where('affiliate_id', $this->affiliate->getKey())
                ->value('status'))->toBe(MembershipStatus::Approved);
    });

    test('local imported offer does not create a link before core approval', function (): void {
        config(['affiliates.owner.enabled' => false]);

        $program = AffiliateProgram::create([
            'name' => 'Approval Marketplace Program',
            'slug' => 'approval-marketplace-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'visibility' => ProgramVisibility::Public,
            'requires_approval' => true,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate_basis_points' => 1000,
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => false,
            'external_program_id' => $program->getKey(),
            'metadata' => ['catalog_source' => 'local'],
        ]);

        $this->actingAs($this->user);
        app(AffiliateMarketplacePage::class)->applyForOffer($offer->id);

        expect(AffiliateProgramMembership::query()
            ->where('program_id', $program->getKey())
            ->where('affiliate_id', $this->affiliate->getKey())
            ->value('status'))->toBe(MembershipStatus::Pending)
            ->and(AffiliateOfferLink::query()
                ->where('offer_id', $offer->getKey())
                ->where('affiliate_id', $this->affiliate->getKey())
                ->exists())->toBeFalse()
            ->and(AffiliateOfferApplication::query()
                ->where('offer_id', $offer->getKey())
                ->where('affiliate_id', $this->affiliate->getKey())
                ->exists())->toBeFalse();
    });
});
