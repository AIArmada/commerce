<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Database\Eloquent\Relations\Relation;

test('network models resolve through short morph aliases', function (): void {
    expect(Relation::getMorphedModel('affiliate_site'))->toBe(AffiliateSite::class)
        ->and(Relation::getMorphedModel('affiliate_offer_category'))->toBe(AffiliateOfferCategory::class)
        ->and(Relation::getMorphedModel('affiliate_offer'))->toBe(AffiliateOffer::class)
        ->and(Relation::getMorphedModel('affiliate_offer_creative'))->toBe(AffiliateOfferCreative::class)
        ->and(Relation::getMorphedModel('affiliate_offer_application'))->toBe(AffiliateOfferApplication::class)
        ->and(Relation::getMorphedModel('affiliate_offer_invitation'))->toBe(AffiliateOfferInvitation::class)
        ->and(Relation::getMorphedModel('affiliate_offer_deal'))->toBe(AffiliateOfferDeal::class)
        ->and(Relation::getMorphedModel('affiliate_offer_link'))->toBe(AffiliateOfferLink::class)
        ->and(Relation::getMorphedModel('affiliate_offer_coupon'))->toBe(AffiliateOfferCoupon::class)
        ->and(Relation::getMorphedModel('network_conversion_leg'))->toBe(NetworkConversionLeg::class)
        ->and(Relation::getMorphedModel('merchant_statement'))->toBe(MerchantStatement::class)
        ->and(Relation::getMorphedModel('creator_postback_endpoint'))->toBe(CreatorPostbackEndpoint::class)
        ->and(Relation::getMorphedModel('creator_postback_delivery'))->toBe(CreatorPostbackDelivery::class)
        ->and((new AffiliateOfferLink)->getMorphClass())->toBe('affiliate_offer_link');
});
