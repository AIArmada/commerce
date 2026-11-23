<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateCreative;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\DeleteCreative;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateCreative;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

describe('creative actions', function (): void {
    test('creates a manual creative and ignores forged provenance', function (): void {
        $offer = AffiliateOffer::factory()->forSite(AffiliateSite::factory()->verified()->create())->create();

        $creative = app(CreateCreative::class)->execute($offer, [
            'name' => 'Manual banner',
            'type' => 'banner',
            'is_active' => true,
            'sort_order' => 1,
            'external_creative_id' => 'forged',
            'source_asset_url' => 'https://forged.example/banner.png',
        ]);

        expect($creative->offer_id)->toBe($offer->id)
            ->and($creative->external_creative_id)->toBeNull()
            ->and($creative->source_asset_url)->toBeNull();
    });

    test('imported creatives only accept operator fields', function (): void {
        $offer = AffiliateOffer::factory()->forSite(AffiliateSite::factory()->verified()->create())->create();
        $imported = $offer->creatives()->create([
            'type' => 'image',
            'name' => 'Merchant hero',
            'external_creative_id' => 'hero',
        ]);

        $updated = app(UpdateCreative::class)->execute($imported, [
            'name' => 'Forged rename',
            'type' => 'text',
            'is_active' => false,
            'sort_order' => 8,
        ]);

        expect($updated->name)->toBe('Merchant hero')
            ->and($updated->type)->toBe(CreativeType::Image)
            ->and($updated->is_active)->toBeFalse()
            ->and($updated->sort_order)->toBe(8);
    });

    test('imported creatives cannot be deleted', function (): void {
        $offer = AffiliateOffer::factory()->forSite(AffiliateSite::factory()->verified()->create())->create();
        $imported = $offer->creatives()->create([
            'type' => 'image',
            'name' => 'Merchant hero',
            'external_creative_id' => 'hero',
        ]);

        expect(fn () => app(DeleteCreative::class)->execute($imported))
            ->toThrow(RuntimeException::class);
    });

    test('manual creatives can be updated and deleted', function (): void {
        $offer = AffiliateOffer::factory()->forSite(AffiliateSite::factory()->verified()->create())->create();
        $creative = app(CreateCreative::class)->execute($offer, [
            'name' => 'Manual banner',
            'type' => 'banner',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $updated = app(UpdateCreative::class)->execute($creative, [
            'name' => 'Renamed banner',
            'type' => 'banner',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        expect($updated->name)->toBe('Renamed banner')
            ->and(app(DeleteCreative::class)->execute($updated))->toBeTrue();
    });

    test('owner-scoped creative writes require the offer owner context', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $owner = User::factory()->create();
        $other = User::factory()->create();

        [$offer, $creative] = OwnerContext::withOwner($owner, function () use ($owner): array {
            $site = AffiliateSite::factory()->verified()->forOwner($owner)->create();
            $offer = AffiliateOffer::factory()->forSite($site)->create();
            $creative = $offer->creatives()->create(['type' => 'text', 'name' => 'Owned creative']);

            return [$offer, $creative];
        });

        expect(fn () => OwnerContext::withOwner($other, fn (): AffiliateOfferCreative => app(UpdateCreative::class)->execute($creative, [
            'name' => 'Cross-owner rename',
            'type' => 'text',
            'is_active' => true,
            'sort_order' => 0,
        ])))->toThrow(ModelNotFoundException::class);

        expect(fn () => OwnerContext::withOwner($other, fn (): bool => app(DeleteCreative::class)->execute($creative)))
            ->toThrow(ModelNotFoundException::class);

        expect(fn () => OwnerContext::withOwner($other, fn (): AffiliateOfferCreative => app(CreateCreative::class)->execute($offer, [
            'name' => 'Cross-owner create',
            'type' => 'text',
            'is_active' => true,
            'sort_order' => 0,
        ])))->toThrow(ModelNotFoundException::class);
    });
});
