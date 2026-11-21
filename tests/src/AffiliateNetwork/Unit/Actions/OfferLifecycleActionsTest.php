<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ArchiveOffer;
use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

describe('offer lifecycle actions', function (): void {
    test('offer form writes stamp lifecycle entries and preserve the stamp on a repeated status', function (): void {
        $site = AffiliateSite::factory()->verified()->create();
        $published = app(CreateOffer::class)->execute($site, [
            'name' => 'Published offer',
            'status' => OfferStatus::Published->value,
        ]);

        expect($published->published_at)->not->toBeNull();

        $this->travel(1)->minute();
        $unchanged = app(UpdateOffer::class)->execute($published, ['status' => OfferStatus::Published->value]);
        $archived = app(UpdateOffer::class)->execute($published, ['status' => OfferStatus::Archived->value]);

        expect($unchanged->published_at)->toEqual($published->published_at)
            ->and($archived->archived_at)->not->toBeNull();
    });

    test('publishes a verified site offer with lifecycle timestamps', function (): void {
        $site = AffiliateSite::factory()->verified()->create();
        $offer = AffiliateOffer::factory()->draft()->forSite($site)->create();

        $published = app(PublishOffer::class)->execute($offer);

        expect($published->status)->toBe(OfferStatus::Published)
            ->and($published->published_at)->not->toBeNull()
            ->and($published->archived_at)->toBeNull()
            ->and($published->isActive())->toBeTrue();
    });

    test('archives a published offer with an archive timestamp', function (): void {
        $site = AffiliateSite::factory()->verified()->create();
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create();

        $archived = app(ArchiveOffer::class)->execute($offer);

        expect($archived->status)->toBe(OfferStatus::Archived)
            ->and($archived->archived_at)->not->toBeNull()
            ->and($archived->isActive())->toBeFalse();
    });

    test('refuses to publish an offer on an unverified site', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $offer = AffiliateOffer::factory()->draft()->forSite($site)->create();

        expect(fn () => app(PublishOffer::class)->execute($offer))
            ->toThrow(ValidationException::class);
    });

    test('owner-scoped lifecycle writes require the offer owner context', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $owner = User::factory()->create();
        $other = User::factory()->create();

        [$site, $offer] = OwnerContext::withOwner($owner, function () use ($owner): array {
            $site = AffiliateSite::factory()->verified()->forOwner($owner)->create();
            $offer = AffiliateOffer::factory()->draft()->forSite($site)->create();

            return [$site, $offer];
        });

        $published = OwnerContext::withOwner($owner, fn (): AffiliateOffer => app(PublishOffer::class)->execute($offer));

        expect($published->status)->toBe(OfferStatus::Published);

        expect(fn () => OwnerContext::withOwner($other, fn (): AffiliateOffer => app(ArchiveOffer::class)->execute($published)))
            ->toThrow(ModelNotFoundException::class);
    });
});
