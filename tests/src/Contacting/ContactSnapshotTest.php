<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\CreateContactSnapshotAction;
use AIArmada\Contacting\Data\ContactSnapshotData;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Customers\Models\Customer;

test('ContactSnapshotData constructor', function () {
    $data = new ContactSnapshotData(
        snapshotType: 'contact_method',
        reason: 'event_public_contact',
        channel: 'email',
        value: 'admin@example.com',
    );

    expect($data->snapshotType)->toBe('contact_method');
    expect($data->reason)->toBe('event_public_contact');
    expect($data->channel)->toBe('email');
    expect($data->value)->toBe('admin@example.com');
});

test('CreateContactSnapshotAction can be instantiated', function () {
    $action = new CreateContactSnapshotAction;
    expect($action)->toBeInstanceOf(CreateContactSnapshotAction::class);
});

test('CreateContactSnapshotAction persists snapshots with the source owner', function () {
    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Owner',
        'email' => 'snapshot-owner-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $contactMethod = $customer->addContactMethod(ContactMethodData::email('snapshot-' . uniqid() . '@example.com'));

    $snapshot = (new CreateContactSnapshotAction)->fromContactMethod($customer, $contactMethod, 'checkout');

    expect($snapshot->exists)->toBeTrue()
        ->and($snapshot->owner_type)->toBe($contactMethod->owner_type)
        ->and($snapshot->owner_id)->toBe($contactMethod->owner_id)
        ->and(ContactSnapshot::query()->whereKey($snapshot->id)->exists())->toBeTrue();
});

test('CreateContactSnapshotAction returns an unsaved snapshot when snapshots are disabled', function () {
    config()->set('contacting.features.contact_snapshots', false);

    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Disabled',
        'email' => 'snapshot-disabled-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $contactMethod = $customer->addContactMethod(ContactMethodData::email('snapshot-disabled-' . uniqid() . '@example.com'));

    $snapshot = (new CreateContactSnapshotAction)->fromContactMethod($customer, $contactMethod, 'checkout');

    expect($snapshot->exists)->toBeFalse()
        ->and($snapshot->owner_type)->toBe($contactMethod->owner_type)
        ->and($snapshot->owner_id)->toBe($contactMethod->owner_id)
        ->and(ContactSnapshot::query()->count())->toBe(0);
});

test('snapshot action methods exist', function () {
    $action = new CreateContactSnapshotAction;
    expect(method_exists($action, 'fromContactMethod'))->toBeTrue();
    expect(method_exists($action, 'fromSocialProfile'))->toBeTrue();
    expect(method_exists($action, 'fromBundle'))->toBeTrue();
});
