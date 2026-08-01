<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Resolvers\DefaultEventSearchRelationProvider;

it('binds the default provider through the package contract', function (): void {
    expect(app(EventSearchRelationProvider::class))
        ->toBeInstanceOf(DefaultEventSearchRelationProvider::class);
});

it('provides only package-owned event search relations by default', function (): void {
    expect((new DefaultEventSearchRelationProvider)->relations())
        ->toBe(['classifications.term', 'timeExpressions']);
});
