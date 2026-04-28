<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});
