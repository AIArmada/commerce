<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

test('NullOwnerResolver returns null', function (): void {
    $resolver = new NullOwnerResolver;

    expect($resolver->resolve())->toBeNull();
});
