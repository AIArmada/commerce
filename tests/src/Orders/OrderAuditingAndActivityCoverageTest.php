<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderAddress;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use OwenIt\Auditing\Contracts\Auditable;

it('order model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Order::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Order::class), true))->toBeTrue();
});

it('order payment model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(OrderPayment::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(OrderPayment::class), true))->toBeTrue();
});

it('order refund model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(OrderRefund::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(OrderRefund::class), true))->toBeTrue();
});

it('order address model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(OrderAddress::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(OrderAddress::class), true))->toBeTrue();
});

it('order item model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(OrderItem::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(OrderItem::class), true))->toBeTrue();
});

it('order note model is activity loggable', function (): void {
    $traits = class_uses_recursive(OrderNote::class);

    expect($traits)->toContain(LogsCommerceActivity::class);
});
