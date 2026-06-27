<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Models\Segment;

it('guards id from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['id' => 'forged-id']);
    expect($customer->id)->toBeNull();
});

it('guards id from mass assignment on address', function (): void {
    $address = new Address;
    $address->fill(['id' => 'forged-id']);
    expect($address->id)->toBeNull();
});

it('guards id from mass assignment on segment', function (): void {
    $segment = new Segment;
    $segment->fill(['id' => 'forged-id']);
    expect($segment->id)->toBeNull();
});

it('guards id from mass assignment on customer group', function (): void {
    $group = new CustomerGroup;
    $group->fill(['id' => 'forged-id']);
    expect($group->id)->toBeNull();
});

it('guards id from mass assignment on customer note', function (): void {
    $note = new CustomerNote;
    $note->fill(['id' => 'forged-id']);
    expect($note->id)->toBeNull();
});
