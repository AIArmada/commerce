<?php

declare(strict_types=1);

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Features\Snapshots\Actions\CreateContactSnapshotAction;
use AIArmada\Contacting\Features\Snapshots\Exceptions\ContactSnapshotsDisabledException;
use AIArmada\Contacting\Features\Snapshots\Models\ContactSnapshot;
use AIArmada\Customers\Models\Customer;

test('CreateContactSnapshotAction persists snapshots with the source owner', function (): void {
    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Owner',
        'email' => 'snapshot-owner-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $contactMethod = $customer->addContactMethod(ContactMethodData::email('snapshot-' . uniqid() . '@example.com'));

    $snapshot = app(CreateContactSnapshotAction::class)->fromContactMethod($customer, $contactMethod->id, 'checkout');

    expect($snapshot->exists)->toBeTrue()
        ->and($snapshot->owner_type)->toBe($contactMethod->owner_type)
        ->and($snapshot->owner_id)->toBe($contactMethod->owner_id)
        ->and(ContactSnapshot::query()->whereKey($snapshot->id)->exists())->toBeTrue();
});

test('CreateContactSnapshotAction throws when snapshots are disabled', function (): void {
    config()->set('contacting.features.contact_snapshots', false);

    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Disabled',
        'email' => 'snapshot-disabled-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $contactMethod = $customer->addContactMethod(ContactMethodData::email('snapshot-disabled-' . uniqid() . '@example.com'));

    expect(fn () => app(CreateContactSnapshotAction::class)->fromContactMethod($customer, $contactMethod->id, 'checkout'))
        ->toThrow(ContactSnapshotsDisabledException::class);

    expect(ContactSnapshot::query()->count())->toBe(0);
});

test('CreateContactSnapshotAction persists social profile snapshots', function (): void {
    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Social',
        'email' => 'snapshot-social-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $profile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        handle: 'snapshot-' . uniqid(),
    ));

    $snapshot = app(CreateContactSnapshotAction::class)->fromSocialProfile($customer, $profile->id, 'checkout');

    expect($snapshot->exists)->toBeTrue()
        ->and($snapshot->snapshot_type)->toBe('social_profile')
        ->and($snapshot->source_id)->toBe($profile->id)
        ->and($snapshot->owner_type)->toBe($profile->owner_type)
        ->and($snapshot->owner_id)->toBe($profile->owner_id);
});

test('snapshot payloads exclude loaded relations', function (): void {
    $customer = Customer::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Payload',
        'email' => 'snapshot-payload-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $method = $customer->addContactMethod(ContactMethodData::email('payload-' . uniqid() . '@example.com'));
    $method->setRelation('contactable', $customer);

    $snapshot = app(CreateContactSnapshotAction::class)->fromContactMethod($customer, $method->id);

    expect($snapshot->payload)->not->toHaveKey('owner')
        ->and($snapshot->payload)->not->toHaveKey('contactable')
        ->and($snapshot->payload['value'])->toBe($method->value);
});
