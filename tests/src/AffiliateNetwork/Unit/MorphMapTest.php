<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Illuminate\Database\Eloquent\Relations\Relation;

test('network models resolve through short morph aliases', function (): void {
    expect(Relation::getMorphedModel('affiliate_offer'))->toBe(AffiliateOffer::class)
        ->and(Relation::getMorphedModel('affiliate_offer_application'))->toBe(AffiliateOfferApplication::class)
        ->and(Relation::getMorphedModel('affiliate_offer_category'))->toBe(AffiliateOfferCategory::class)
        ->and(Relation::getMorphedModel('affiliate_offer_creative'))->toBe(AffiliateOfferCreative::class)
        ->and(Relation::getMorphedModel('affiliate_offer_link'))->toBe(AffiliateOfferLink::class)
        ->and(Relation::getMorphedModel('affiliate_site'))->toBe(AffiliateSite::class)
        ->and(Relation::getMorphedModel('network_conversion_leg'))->toBe(NetworkConversionLeg::class)
        ->and((new AffiliateOfferLink)->getMorphClass())->toBe('affiliate_offer_link');
});
