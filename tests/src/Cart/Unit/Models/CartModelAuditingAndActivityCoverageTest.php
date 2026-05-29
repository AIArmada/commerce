<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use OwenIt\Auditing\Contracts\Auditable;

it('cart model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(CartModel::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(CartModel::class), true))->toBeTrue();
});

it('condition model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Condition::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Condition::class), true))->toBeTrue();
});
