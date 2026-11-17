<?php

declare(strict_types=1);

use AIArmada\Checkout\Support\CheckoutStepOrderPolicy;

it('keeps scoped capacity holds ahead of payment and confirmation after final checkout work', function (): void {
    $policy = new CheckoutStepOrderPolicy;

    $order = $policy->enforceStepDependencyOrder([
        'process_payment',
        'create_order',
        'commit_scoped_capacity',
        'dispatch_documents',
        'reserve_scoped_capacity',
        'persist_customer',
    ]);

    $position = static fn (string $name): int => array_search($name, $order, true);

    expect($position('reserve_scoped_capacity'))->toBeLessThan($position('process_payment'))
        ->and($position('persist_customer'))->toBeLessThan($position('create_order'))
        ->and($position('create_order'))->toBeLessThan($position('commit_scoped_capacity'))
        ->and($position('dispatch_documents'))->toBeLessThan($position('commit_scoped_capacity'));
});
