<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;

beforeEach(function (): void {
    $this->resolver = new WildcardPermissionResolver;
});

describe('isWildcard', function (): void {
    it('returns true for wildcard patterns', function (): void {
        expect($this->resolver->isWildcard('*'))->toBeTrue();
        expect($this->resolver->isWildcard('orders.*'))->toBeTrue();
        expect($this->resolver->isWildcard('*.view'))->toBeTrue();
    });

    it('returns false for regular permissions', function (): void {
        expect($this->resolver->isWildcard('orders.view'))->toBeFalse();
        expect($this->resolver->isWildcard('users.create'))->toBeFalse();
    });
});

describe('matches', function (): void {
    it('matches exact permissions', function (): void {
        expect($this->resolver->matches('orders.view', 'orders.view'))->toBeTrue();
    });

    it('matches universal wildcard', function (): void {
        expect($this->resolver->matches('*', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('*', 'anything.here'))->toBeTrue();
    });

    it('matches prefix wildcards', function (): void {
        expect($this->resolver->matches('orders.*', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('orders.*', 'orders.create'))->toBeTrue();
        expect($this->resolver->matches('orders.*', 'orders.delete'))->toBeTrue();
    });

    it('does not match different prefixes', function (): void {
        expect($this->resolver->matches('orders.*', 'users.view'))->toBeFalse();
        expect($this->resolver->matches('orders.*', 'products.create'))->toBeFalse();
    });

    it('matches suffix wildcards', function (): void {
        expect($this->resolver->matches('*.view', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('*.view', 'products.view'))->toBeTrue();
    });

    it('does not match different suffixes', function (): void {
        expect($this->resolver->matches('*.view', 'orders.create'))->toBeFalse();
    });
});
