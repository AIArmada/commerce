<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

it('checkout session is activity loggable', function (): void {
    $traits = class_uses_recursive(CheckoutSession::class);

    expect($traits)->toContain(LogsCommerceActivity::class);
});
