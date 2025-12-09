<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

it('null owner resolver returns null', function (): void {
    $resolver = new NullOwnerResolver;
    expect($resolver->resolve())->toBeNull();
});
