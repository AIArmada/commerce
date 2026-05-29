<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use AIArmada\Jnt\Models\JntOrderParcel;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use OwenIt\Auditing\Contracts\Auditable;

it('jnt order aggregate models are auditable and activity loggable', function (): void {
    $models = [JntOrder::class, JntOrderItem::class, JntOrderParcel::class];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});

it('jnt tracking and webhook models are activity loggable only', function (): void {
    $models = [JntTrackingEvent::class, JntWebhookLog::class];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(LogsCommerceActivity::class)
            ->and($traits)->not->toContain(HasCommerceAudit::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeFalse();
    }
});
