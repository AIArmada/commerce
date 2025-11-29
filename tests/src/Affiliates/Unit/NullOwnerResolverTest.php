<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\Resolvers\NullOwnerResolver;

test('NullOwnerResolver returns null', function (): void {
    $resolver = new NullOwnerResolver();

    expect($resolver->resolveCurrentOwner())->toBeNull();
});