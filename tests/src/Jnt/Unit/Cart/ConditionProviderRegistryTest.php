<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Jnt\Cart\JntShippingConditionProvider;

it('registers JNT shipping condition provider with cart registry', function (): void {
    $registry = app(ConditionProviderRegistry::class);

    expect($registry->providerKeys())->toContain(JntShippingConditionProvider::class);
});
