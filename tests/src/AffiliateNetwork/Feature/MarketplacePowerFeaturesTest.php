<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Events\OfferInvitationCreated;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\AssignOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RevokeOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\AcceptOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\InviteAffiliateToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\UpsertOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\Event;

function marketplaceAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'MKT' . uniqid(),
        'handle' => 'market-' . uniqid(),
        'name' => 'Marketplace Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

function marketplaceOffer(array $attributes = []): AffiliateOffer
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'market-' . uniqid() . '.example']);

    return AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
        'requires_approval' => true,
        'visibility' => OfferVisibility::Public,
    ], $attributes));
}

describe('power feature: private offers and invitations', function (): void {
    test('a private offer rejects uninvited affiliates and accepts a token-gated invitation', function (): void {
        Event::fake([OfferInvitationCreated::class]);

        $offer = marketplaceOffer(['visibility' => OfferVisibility::Private]);
        $affiliate = marketplaceAffiliate();

        expect(fn () => app(ApplyToOffer::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey()))
            ->toThrow(RuntimeException::class, 'invite-only');

        $invitation = app(InviteAffiliateToOffer::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey(), null, null, true);

        Event::assertDispatched(OfferInvitationCreated::class);

        $application = app(AcceptOfferInvitation::class)->execute($invitation->token, (string) $affiliate->getKey());

        expect($application->status)->toBe(ApplicationStatus::Approved)
            ->and($invitation->fresh()->accepted_at)->not->toBeNull();
    });

    test('token-only invitation grants access without a prefilled affiliate id', function (): void {
        $offer = marketplaceOffer(['visibility' => OfferVisibility::Private]);
        $affiliate = marketplaceAffiliate();

        $invitation = app(InviteAffiliateToOffer::class)->execute((string) $offer->getKey(), null, null, null, false);
        $application = app(ApplyToOffer::class)->execute(
            (string) $offer->getKey(),
            (string) $affiliate->getKey(),
            null,
            $invitation->token,
        );

        expect($application->status)->toBe(ApplicationStatus::Pending)
            ->and($application->invitation_id)->toBe($invitation->getKey());
    });

    test('revoking an invitation invalidates it', function (): void {
        $offer = marketplaceOffer(['visibility' => OfferVisibility::Private]);
        $affiliate = marketplaceAffiliate();
        $invitation = app(InviteAffiliateToOffer::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey());

        $revoked = app(RevokeOfferInvitation::class)->execute($invitation);

        expect($revoked->revoked_at)->not->toBeNull()
            ->and($revoked->isActive())->toBeFalse();

        expect(fn () => app(AcceptOfferInvitation::class)->execute($invitation->token, (string) $affiliate->getKey()))
            ->toThrow(RuntimeException::class);
    });
});

describe('power feature: custom deals override conversion rates', function (): void {
    test('a custom deal rate overrides the offer rate on conversion', function (): void {
        $offer = marketplaceOffer(['rate_base_bp' => 1000, 'requires_approval' => false]);
        $affiliate = marketplaceAffiliate();

        app(UpsertOfferDeal::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey(), [
            'rate_base_bp' => 3000,
        ]);

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'DEAL-1',
        );

        expect($leg->commission_minor)->toBe(3000);
    });

    test('revoking a deal restores the offer rate', function (): void {
        $offer = marketplaceOffer(['rate_base_bp' => 1000, 'requires_approval' => false]);
        $affiliate = marketplaceAffiliate();

        $deal = app(UpsertOfferDeal::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey(), [
            'rate_base_bp' => 3000,
        ]);
        app(RevokeOfferDeal::class)->execute($deal);

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'DEAL-2',
        );

        expect(AffiliateOfferDeal::query()->where('offer_id', $offer->getKey())->where('is_active', true)->exists())->toBeFalse()
            ->and($leg->commission_minor)->toBe(1000);
    });
});

describe('power feature: linkless promo-code attribution', function (): void {
    test('assigning and revoking a coupon toggles linkless attribution', function (): void {
        $offer = marketplaceOffer(['requires_approval' => false]);
        $affiliate = marketplaceAffiliate();

        $coupon = app(AssignOfferCoupon::class)->execute(
            (string) $offer->getKey(),
            (string) $affiliate->getKey(),
            'spring-save-10',
        );

        expect($coupon->code)->toBe('SPRING-SAVE-10')
            ->and($coupon->site_id)->toBe($offer->site_id);

        app(RevokeOfferCoupon::class)->execute((string) $coupon->getKey());

        expect($coupon->fresh()->is_active)->toBeFalse();
    });

    test('a coupon-only conversion records without a link', function (): void {
        $offer = marketplaceOffer(['requires_approval' => false]);
        $affiliate = marketplaceAffiliate();
        app(AssignOfferCoupon::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey(), 'PROMO1');

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'COUPON-1',
            couponId: 'coupon-uuid',
            couponCode: 'PROMO1',
        );

        expect($leg->link_id)->toBeNull()
            ->and($leg->coupon_code)->toBe('PROMO1')
            ->and($leg->status)->toBe(LegStatus::Posted)
            ->and(AffiliateOfferCoupon::query()->where('code', 'PROMO1')->exists())->toBeTrue();
    });

    test('coupon codes are unique per site', function (): void {
        $offer = marketplaceOffer(['requires_approval' => false]);
        $affiliate = marketplaceAffiliate();
        app(AssignOfferCoupon::class)->execute((string) $offer->getKey(), (string) $affiliate->getKey(), 'DUP10');

        expect(fn () => app(AssignOfferCoupon::class)->execute(
            (string) $offer->getKey(),
            (string) marketplaceAffiliate()->getKey(),
            'DUP10',
        ))->toThrow(RuntimeException::class);
    });
});
