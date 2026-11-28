<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorizationItem;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentEvent;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentItem;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentLabel;
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
