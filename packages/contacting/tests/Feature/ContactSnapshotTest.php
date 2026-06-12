<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\CreateContactSnapshotAction;
use AIArmada\Contacting\Data\ContactSnapshotData;

test('ContactSnapshotData constructor', function (): void {
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

test('CreateContactSnapshotAction can be instantiated', function (): void {
    $action = new CreateContactSnapshotAction;
    expect($action)->toBeInstanceOf(CreateContactSnapshotAction::class);
});

test('snapshot action methods exist', function (): void {
    $action = new CreateContactSnapshotAction;
    expect(method_exists($action, 'fromContactMethod'))->toBeTrue();
    expect(method_exists($action, 'fromSocialProfile'))->toBeTrue();
    expect(method_exists($action, 'fromBundle'))->toBeTrue();
});
