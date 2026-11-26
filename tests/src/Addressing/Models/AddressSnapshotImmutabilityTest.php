<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressSnapshotWriter;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\AddressLifecycle\Models\AddressSnapshot;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->snapshotable = new class extends Model {
        protected $table = 'test_models';
    };
    $this->snapshotable->save();

    $this->action = app(AddressSnapshotWriter::class);
});

it('rejects updates to existing snapshots', function (): void {
    $address = Address::query()->create([
        'line1' => 'Original Line',
        'city' => 'Kuala Lumpur',
        'country_code' => 'MY',
    ]);
    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), (string) $address->getKey(), reason: 'order_placed'));

    expect(fn (): mixed => $snapshot->update(['line1' => 'Changed Line']))
        ->toThrow(LogicException::class, 'immutable');

    $snapshot->line1 = 'Changed Again';

    expect(fn (): mixed => $snapshot->save())->toThrow(LogicException::class, 'immutable');

    expect($snapshot->fresh()?->line1)->toBe('Original Line');
});

it('still allows snapshot creation and deletion', function (): void {
    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute(
        $this->snapshotable->getMorphClass(),
        $this->snapshotable->getKey(),
        AddressData::from(['line1' => '123 Main St', 'countryCode' => 'MY']),
        reason: 'order_placed',
    ));

    expect($snapshot->exists)->toBeTrue()
        ->and(AddressSnapshot::query()->whereKey($snapshot->getKey())->exists())->toBeTrue();

    $snapshot->delete();

    expect(AddressSnapshot::query()->whereKey($snapshot->getKey())->exists())->toBeFalse();
});

it('freezes formatted lines from the address model payload', function (): void {
    $address = Address::query()->create([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'country_code' => 'MY',
    ])->fresh();

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), (string) $address->getKey(), reason: 'order_placed'));

    expect($snapshot->formatted_address)->toBe($address->formatted_address)
        ->and($snapshot->formatted_lines)->toBe(explode("\n", (string) $snapshot->formatted_address))
        ->and($snapshot->formatted_lines)->not->toBeEmpty();
});

it('freezes multiline formatted lines from address data', function (): void {
    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute(
        $this->snapshotable->getMorphClass(),
        $this->snapshotable->getKey(),
        AddressData::from([
            'line1' => '123 Main St',
            'countryCode' => 'MY',
            'formatted' => "123 Main St\n50450 Kuala Lumpur\nMalaysia",
        ]),
        reason: 'order_placed',
    ));

    expect($snapshot->formatted_address)->toBe("123 Main St\n50450 Kuala Lumpur\nMalaysia")
        ->and($snapshot->formatted_lines)->toBe(['123 Main St', '50450 Kuala Lumpur', 'Malaysia']);
});

it('stores null formatted lines when formatting is empty', function (): void {
    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute(
        $this->snapshotable->getMorphClass(),
        $this->snapshotable->getKey(),
        AddressData::from(['countryCode' => 'MY']),
        reason: 'order_placed',
    ));

    expect($snapshot->formatted_address)->toBeNull()
        ->and($snapshot->formatted_lines)->toBeNull();
});
