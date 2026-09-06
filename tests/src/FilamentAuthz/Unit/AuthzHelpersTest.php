<?php

declare(strict_types=1);

describe('authz impersonation helpers', function (): void {
    it('exposes only the canonical helper signatures', function (): void {
        $isImpersonating = new ReflectionFunction('AIArmada\Authz\is_impersonating');
        $canImpersonate = new ReflectionFunction('AIArmada\Authz\can_impersonate');
        $canBeImpersonated = new ReflectionFunction('AIArmada\Authz\can_be_impersonated');

        expect($isImpersonating->getNumberOfParameters())->toBe(0)
            ->and($canImpersonate->getNumberOfParameters())->toBe(1)
            ->and($canImpersonate->getParameters()[0]->isOptional())->toBeTrue()
            ->and($canBeImpersonated->getNumberOfParameters())->toBe(2)
            ->and($canBeImpersonated->getParameters()[1]->isOptional())->toBeTrue();
    });

});
