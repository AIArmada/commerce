<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Exceptions\AffiliateProfileRequired;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Support\AffiliateProfileGuard;
use AIArmada\AffiliateNetwork\Support\UserKeyAffiliateIdentityResolver;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Models\Link;

/**
 * @return array{AffiliateSite, AffiliateOffer, AffiliateOfferCreative}
 */
function affiliateProfileRequiredFixtures(User $owner, string $domain): array
{
    return OwnerContext::withOwner($owner, function () use ($owner, $domain): array {
        $site = AffiliateSite::factory()->verified()->forOwner($owner)->create(['domain' => $domain]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'requires_approval' => false,
        ]);
        $creative = $offer->creatives()->create([
            'type' => AffiliateOfferCreative::TYPE_TEXT,
            'name' => 'Profile requirement creative',
            'destination_url' => 'https://' . $domain . '/creative',
        ]);

        return [$site, $offer, $creative];
    });
}

function affiliateProfileRequiredUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => mb_strtolower(str_replace(' ', '-', $name)) . '-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

describe('affiliate profile requirement for network writes', function (): void {
    beforeEach(function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);
    });

    test('user-only actors receive a clear exception before each affiliate-scoped write', function (): void {
        $actor = affiliateProfileRequiredUser('User Only Creator');
        [, $offer, $creative] = affiliateProfileRequiredFixtures($actor, 'user-only.example');
        $message = 'Complete affiliate registration before writing affiliate-owned network records';

        expect(fn () => OwnerContext::withOwner($actor, fn () => app(ApplyToOffer::class)
            ->execute($offer, (string) $actor->getKey())))
            ->toThrow(AffiliateProfileRequired::class, $message);

        expect(fn () => OwnerContext::withOwner($actor, fn () => app(OfferLinkService::class)
            ->createLink($offer, (string) $actor->getKey())))
            ->toThrow(AffiliateProfileRequired::class, $message);

        expect(fn () => OwnerContext::withOwner($actor, fn () => app(OfferLinkService::class)
            ->createCreativeLink($creative, (string) $actor->getKey())))
            ->toThrow(AffiliateProfileRequired::class, $message);

        $unpersistedLink = new AffiliateOfferLink(['affiliate_id' => (string) $actor->getKey()]);
        expect(fn () => OwnerContext::withOwner($actor, fn () => app(NetworkBooks::class)
            ->post($unpersistedLink, 1000, 'MYR', 'PROFILE-REQUIRED-LEG')))
            ->toThrow(AffiliateProfileRequired::class, $message);

        expect(AffiliateOfferApplication::query()->withoutOwnerScope()->count())->toBe(0)
            ->and(AffiliateOfferLink::query()->withoutOwnerScope()->count())->toBe(0)
            ->and(NetworkConversionLeg::query()->withoutOwnerScope()->count())->toBe(0)
            ->and(Link::query()->count())->toBe(0);
    });

    test('engine owner mode requires a merchant profile when network owner mode is off', function (): void {
        config(['affiliate-network.owner.enabled' => false]);

        $actor = affiliateProfileRequiredUser('Engine Owner Mode Creator');
        [, $offer] = affiliateProfileRequiredFixtures($actor, 'engine-owner-mode.example');

        expect(fn () => OwnerContext::withOwner($actor, fn () => app(ApplyToOffer::class)
            ->execute($offer, 'missing-affiliate-profile')))
            ->toThrow(AffiliateProfileRequired::class);
    });

    test('an actor with a merchant Affiliate row can apply and create direct and creative links', function (): void {
        $owner = affiliateProfileRequiredUser('Registered Creator Owner');
        [, $offer, $creative] = affiliateProfileRequiredFixtures($owner, 'registered-profile.example');
        $affiliate = OwnerContext::withOwner($owner, fn (): Affiliate => Affiliate::forceCreate([
            'code' => 'PROFILE' . uniqid(),
            'handle' => 'profile-' . uniqid(),
            'name' => 'Registered Creator',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]));

        OwnerContext::withOwner($owner, function () use ($affiliate, $offer, $creative): void {
            $application = app(ApplyToOffer::class)->execute($offer, (string) $affiliate->getKey());
            $link = app(OfferLinkService::class)->createLink($offer, (string) $affiliate->getKey());
            $creativeLink = app(OfferLinkService::class)->createCreativeLink($creative, (string) $affiliate->getKey());
            $leg = app(NetworkBooks::class)->post($link, 1200, 'MYR', 'PROFILE-POSTED-LEG');

            expect($application->affiliate_id)->toBe((string) $affiliate->getKey())
                ->and($link->affiliate_id)->toBe((string) $affiliate->getKey())
                ->and($creativeLink->metadata['creative_id'])->toBe($creative->getKey())
                ->and($leg->affiliate_id)->toBe((string) $affiliate->getKey());
        });
    });

    test('combined installs keep user fallback writes when network owner mode is off', function (): void {
        config([
            'affiliate-network.owner.enabled' => false,
            'affiliates.owner.enabled' => false,
        ]);

        $actor = affiliateProfileRequiredUser('Owner Off Creator');
        [, $offer] = affiliateProfileRequiredFixtures($actor, 'owner-off.example');

        OwnerContext::withOwner($actor, function () use ($actor, $offer): void {
            $application = app(ApplyToOffer::class)->execute($offer, (string) $actor->getKey());
            $link = app(OfferLinkService::class)->createLink($offer, (string) $actor->getKey());

            expect($application->affiliate_id)->toBe((string) $actor->getKey())
                ->and($link->affiliate_id)->toBe((string) $actor->getKey());
        });

        expect(Affiliate::query()->withoutOwnerScope()->whereKey($actor->getKey())->exists())->toBeFalse();
    });

    test('standalone UserKey identity bypasses the profile gate and keeps owner-off writes', function (): void {
        app()->instance(AffiliateIdentityResolver::class, new UserKeyAffiliateIdentityResolver);
        app()->forgetInstance(OfferLinkService::class);
        app()->forgetInstance(ApplyToOffer::class);
        app()->forgetInstance(AffiliateProfileGuard::class);
        config(['affiliate-network.models.affiliate' => User::class]);

        $actor = affiliateProfileRequiredUser('Standalone Creator');
        [, $offer] = affiliateProfileRequiredFixtures($actor, 'standalone-profile.example');
        $guard = app(AffiliateProfileGuard::class);

        $guard->assertCanWriteFor((string) $actor->getKey());

        config([
            'affiliate-network.owner.enabled' => false,
            'affiliates.owner.enabled' => false,
        ]);

        OwnerContext::withOwner($actor, function () use ($actor, $offer): void {
            $application = app(ApplyToOffer::class)->execute($offer, (string) $actor->getKey());
            $link = app(OfferLinkService::class)->createLink($offer, (string) $actor->getKey());

            expect($application->affiliate_id)->toBe((string) $actor->getKey())
                ->and($link->affiliate_id)->toBe((string) $actor->getKey());
        });
    });
});
