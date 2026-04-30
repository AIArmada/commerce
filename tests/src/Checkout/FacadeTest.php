<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Facades\Checkout;
use Illuminate\Support\Facades\Facade;

describe('Checkout Facade', function (): void {
    it('has correct facade accessor', function (): void {
        $reflection = new ReflectionClass(Checkout::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        expect($method->invoke(null))->toBe(CheckoutServiceInterface::class);
    });

    it('extends Laravel Facade class', function (): void {
        expect(Checkout::class)->toExtend(Facade::class);
    });

    it('is a final class', function (): void {
        $reflection = new ReflectionClass(Checkout::class);

        expect($reflection->isFinal())->toBeTrue();
    });
});
