<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;

beforeEach(function () {
    $this->resolver = new WildcardPermissionResolver;
});

describe('isWildcard', function () {
    it('returns true for wildcard patterns', function () {
        expect($this->resolver->isWildcard('*'))->toBeTrue();
        expect($this->resolver->isWildcard('orders.*'))->toBeTrue();
        expect($this->resolver->isWildcard('*.view'))->toBeTrue();
    });

    it('returns false for regular permissions', function () {
        expect($this->resolver->isWildcard('orders.view'))->toBeFalse();
        expect($this->resolver->isWildcard('users.create'))->toBeFalse();
    });
});

describe('matches', function () {
    it('matches exact permissions', function () {
        expect($this->resolver->matches('orders.view', 'orders.view'))->toBeTrue();
    });

    it('matches universal wildcard', function () {
        expect($this->resolver->matches('*', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('*', 'anything.here'))->toBeTrue();
    });

    it('matches prefix wildcards', function () {
        expect($this->resolver->matches('orders.*', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('orders.*', 'orders.create'))->toBeTrue();
        expect($this->resolver->matches('orders.*', 'orders.delete'))->toBeTrue();
    });

    it('does not match different prefixes', function () {
        expect($this->resolver->matches('orders.*', 'users.view'))->toBeFalse();
        expect($this->resolver->matches('orders.*', 'products.create'))->toBeFalse();
    });

    it('matches suffix wildcards', function () {
        expect($this->resolver->matches('*.view', 'orders.view'))->toBeTrue();
        expect($this->resolver->matches('*.view', 'products.view'))->toBeTrue();
    });

    it('does not match different suffixes', function () {
        expect($this->resolver->matches('*.view', 'orders.create'))->toBeFalse();
    });
});
