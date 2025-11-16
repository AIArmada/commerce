<?php

declare(strict_types=1);

use AIArmada\Vouchers\Support\Resolvers\NullOwnerResolver;

it('null owner resolver returns null', function (): void {
    $resolver = new NullOwnerResolver();
    expect($resolver->resolve())->toBeNull();
});
