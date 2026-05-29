<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\ReturnAuthorizationItem;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentEvent;
use AIArmada\Shipping\Models\ShipmentItem;
use AIArmada\Shipping\Models\ShipmentLabel;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use OwenIt\Auditing\Contracts\Auditable;

it('shipping core models are auditable and activity loggable', function (): void {
    $models = [
        Shipment::class,
        ReturnAuthorization::class,
        ReturnAuthorizationItem::class,
        ShipmentLabel::class,
        ShippingRate::class,
        ShippingZone::class,
        ShipmentItem::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});

it('shipment event model is activity loggable', function (): void {
    $traits = class_uses_recursive(ShipmentEvent::class);

    expect($traits)->toContain(LogsCommerceActivity::class);
});
