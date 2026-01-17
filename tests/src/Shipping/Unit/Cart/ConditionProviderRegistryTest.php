<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Shipping\Cart\ShippingConditionProvider;

it('registers shipping condition provider with cart registry', function (): void {
    $registry = app(ConditionProviderRegistry::class);

    expect($registry->providerKeys())->toContain(ShippingConditionProvider::class);
});
