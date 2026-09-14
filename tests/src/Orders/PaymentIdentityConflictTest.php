<?php

declare(strict_types=1);

use AIArmada\Orders\Models\OrderPayment;
use Illuminate\Database\QueryException;

it('converts a raced identity write into a domain exception', function (): void {
    $payment = new OrderPayment([
        'gateway' => 'stripe',
        'transaction_id' => 'txn_race',
    ]);

    $convert = new ReflectionMethod(OrderPayment::class, 'convertIdentityConflict');

    $duplicate = new QueryException('testing', 'insert into order_payments', [], new Exception('duplicate', 23000));

    expect(fn () => $convert->invoke($payment, $duplicate))
        ->toThrow(InvalidArgumentException::class, 'already exists');

    $other = new QueryException('testing', 'insert into order_payments', [], new Exception('boom', 12345));

    expect(fn () => $convert->invoke($payment, $other))
        ->toThrow(QueryException::class);
});
