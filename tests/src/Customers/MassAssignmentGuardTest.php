<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
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

it('guards user_id from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['user_id' => 'forged-user-id']);
    expect($customer->user_id)->toBeNull();
});

it('guards status from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['status' => 'suspended']);
    expect($customer->status->value)->toBe('active');
});

it('guards is_guest from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['is_guest' => true]);
    expect($customer->is_guest)->toBeFalse();
});

it('guards accepts_marketing from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['accepts_marketing' => true]);
    expect($customer->accepts_marketing)->toBeFalse();
});

it('guards timestamps from mass assignment on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00']);
    expect($customer->created_at)->toBeNull()
        ->and($customer->updated_at)->toBeNull();
});

it('allows mass assignment of profile name fields on customer', function (): void {
    $customer = new Customer;
    $customer->fill(['first_name' => 'Jane', 'last_name' => 'Doe', 'company' => 'Acme']);
    expect($customer->first_name)->toBe('Jane')
        ->and($customer->last_name)->toBe('Doe')
        ->and($customer->company)->toBe('Acme');
});
