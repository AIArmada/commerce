<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressSnapshotWriter;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressSnapshot;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->action = app(AddressSnapshotWriter::class);
});

it('snapshots address data from AddressData', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $addressData = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'countryCode' => 'MY',
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), $addressData, reason: 'test'));

    expect($snapshot)->toBeInstanceOf(AddressSnapshot::class);
    expect($snapshot->line1)->toBe('123 Main St');
    expect($snapshot->city)->toBe('Kuala Lumpur');
    expect($snapshot->postcode)->toBe('50450');
    expect($snapshot->country_code)->toBe('MY');
    expect($snapshot->reason)->toBe('test');
    expect($snapshot->address_id)->toBeNull();
});

it('rejects an empty snapshot reason', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    expect(fn (): string => $this->action->execute(
        $snapshotable->getMorphClass(),
        $snapshotable->getKey(),
        AddressData::from(['line1' => '123 Main St', 'countryCode' => 'MY']),
        reason: '   ',
    ))->toThrow(InvalidArgumentException::class, 'non-empty string');
});

it('snapshots address from Address model', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'country_code' => 'MY',
        'google_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), (string) $address->getKey(), reason: 'order_placed'));

    expect($snapshot->line1)->toBe('123 Main St');
    expect($snapshot->city)->toBe('Kuala Lumpur');
    expect($snapshot->address_id)->toBe($address->id);
    expect($snapshot->google_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('snapshots the address label from AddressData and Address', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'label' => 'Warehouse',
        'line1' => '123 Main St',
        'country_code' => 'MY',
    ]);

    $modelSnapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), (string) $address->getKey()));
    $dataSnapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), AddressData::from([
        'label' => 'Billing',
        'line1' => '456 Main St',
        'countryCode' => 'MY',
    ])));

    expect($modelSnapshot->label)->toBe('Warehouse')
        ->and($dataSnapshot->label)->toBe('Billing');
});

it('snapshot copies latitude and longitude from address', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), (string) $address->getKey(), reason: 'test'));

    expect($snapshot->latitude)->toBe(3.1712);
    expect($snapshot->longitude)->toBe(101.6678);
});

it('snapshot remains unchanged when original address changes', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => 'Original Line',
        'city' => 'Original City',
        'postcode' => '12345',
        'country_code' => 'MY',
        'google_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), (string) $address->getKey(), reason: 'order_placed'));

    $address->update([
        'line1' => 'Changed Line',
        'city' => 'Changed City',
        'google_place_id' => 'new-place-id',
    ]);

    $snapshot->refresh();

    expect($snapshot->line1)->toBe('Original Line');
    expect($snapshot->city)->toBe('Original City');
    expect($snapshot->google_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});
