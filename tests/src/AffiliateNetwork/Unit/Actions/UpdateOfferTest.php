<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

describe('UpdateOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(UpdateOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->forSite($this->site)->create([
            'name' => 'Original Name',
            'description' => 'Original description',
        ]);
    });

    test('updates offer fields', function (): void {
        $updated = $this->action->execute($this->offer, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        expect($updated->name)->toBe('Updated Name');
        expect($updated->description)->toBe('Updated description');
    });

    test('returns fresh model instance', function (): void {
        $updated = $this->action->execute($this->offer, [
            'name' => 'Updated',
        ]);

        expect($updated->name)->toBe('Updated');
        expect($updated->isDirty())->toBeFalse();
    });

    test('dispatches OfferUpdated event', function (): void {
        Event::fake();

        $this->action->execute($this->offer, ['name' => 'Event Test']);

        Event::assertDispatched(OfferUpdated::class);
    });

    test('drops unknown fields instead of mass assigning them', function (): void {
        $updated = $this->action->execute($this->offer, [
            'name' => 'Updated Name',
            'not_a_column' => 'ignored',
        ]);

        expect($updated->name)->toBe('Updated Name');
    });

    test('moves offer to another accessible site', function (): void {
        $otherSite = AffiliateSite::factory()->verified()->create();

        $updated = $this->action->execute($this->offer, [
            'site_id' => $otherSite->id,
        ]);

        expect($updated->site_id)->toBe($otherSite->id);
    });

    test('rejects move to a missing site', function (): void {
        $this->action->execute($this->offer, [
            'site_id' => (string) Str::uuid(),
        ]);
    })->throws(ModelNotFoundException::class);

    test('rejects move to a site outside the owner scope', function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliate-network.owner.include_global' => false,
        ]);

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $offer = OwnerContext::withOwner($owner, function () use ($owner) {
            $site = AffiliateSite::factory()->verified()->forOwner($owner)->create();

            return AffiliateOffer::factory()->forSite($site)->create();
        });

        $foreignSite = OwnerContext::withOwner($otherOwner, fn () => AffiliateSite::factory()
            ->verified()
            ->forOwner($otherOwner)
            ->create());

        OwnerContext::withOwner($owner, fn () => $this->action->execute($offer, [
            'site_id' => $foreignSite->id,
        ]));
    })->throws(AuthorizationException::class);
});
