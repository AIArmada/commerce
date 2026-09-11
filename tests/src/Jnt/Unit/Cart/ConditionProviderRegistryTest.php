<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Jnt\Cart\JntShippingCalculator;

it('registers the unified JNT shipping calculator with the cart registry', function (): void {
    $registry = app(ConditionProviderRegistry::class);

    expect($registry->providerKeys())->toContain(JntShippingCalculator::class);
});
