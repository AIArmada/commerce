<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use OwenIt\Auditing\Contracts\Auditable;

it('affiliate-network core models are auditable and activity loggable', function (): void {
    $models = [
        AffiliateOffer::class,
        AffiliateOfferApplication::class,
        AffiliateOfferCategory::class,
        AffiliateOfferCreative::class,
        AffiliateOfferLink::class,
        AffiliateSite::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});
