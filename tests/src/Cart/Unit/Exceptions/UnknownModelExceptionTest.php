<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\CartException;
use AIArmada\Cart\Exceptions\UnknownModelException;

it('uses the default message and cart exception hierarchy', function (): void {
    $exception = new UnknownModelException;

    expect($exception->getMessage())->toBe('Unknown model class')
        ->and($exception)->toBeInstanceOf(CartException::class);
});
