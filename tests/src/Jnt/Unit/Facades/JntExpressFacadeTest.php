<?php

declare(strict_types=1);

use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Services\JntExpressService;

describe('JntExpress facade', function (): void {
    it('returns correct facade accessor', function (): void {
        // Use reflection to test the protected method
        $reflection = new ReflectionClass(JntExpress::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        $accessor = $method->invoke(null);

        expect($accessor)->toBe('jnt-express');
    });

    it('has facade root bound in container', function (): void {
        // Verify that the service is bound in the container
        expect(app()->bound('jnt-express'))->toBeTrue();
    });

    it('resolves to JntExpressService', function (): void {
        $service = app('jnt-express');

        expect($service)->toBeInstanceOf(JntExpressService::class);
    });
});
