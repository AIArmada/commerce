<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage;
use Livewire\Livewire;

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

        Livewire::test(AffiliateMarketplacePage::class)
            ->call('applyForOffer', $offer->id, 'I have a strong audience');

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

        Livewire::test(AffiliateMarketplacePage::class)
            ->call('applyForOffer', $offer->id, 'unused reason');

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

        Livewire::test(AffiliateMarketplacePage::class)
            ->call('applyForOffer', $offer->id, 'I should not be able to apply');

        expect(AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();

        expect(AffiliateOfferLink::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $this->affiliate->id)
            ->exists())->toBeFalse();
    });
});