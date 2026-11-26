<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressSnapshotWriter;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Addressable;
use AIArmada\Addressing\Features\AddressLifecycle\Models\AddressSnapshot;
use AIArmada\Addressing\Features\AddressLifecycle\Traits\HasAddresses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seedCountry('MY');
});

it('generates formatted output on create when none is supplied', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'country_code' => 'MY',
    ])->fresh();

    expect($address->formatted_address)->toContain('123 Main St');
    expect($address->formatted_lines)->toBe(explode("\n", (string) $address->formatted_address));
});

it('regenerates formatted output when address inputs change but preserves explicit values', function (): void {
    $address = Address::create(['line1' => 'Old line', 'country_code' => 'MY']);
    $original = $address->fresh()->formatted_address;

    $address->update(['line1' => 'New line']);

    expect($address->fresh()->formatted_address)->not->toBe($original);
    expect($address->fresh()->formatted_address)->toContain('New line');

    $address->update(['line1' => 'Another line', 'formatted_address' => 'Hand-written']);

    expect($address->fresh()->formatted_address)->toBe('Hand-written');
});

it('skips normalization when only non-address attributes change', function (): void {
    $address = Address::create(['line1' => 'Somewhere', 'country' => 'Malaysia']);

    expect($address->fresh()->country_id)->not->toBeNull();

    Address::query()->whereKey($address->getKey())->update(['country_id' => null]);
    $address->update(['metadata' => ['note' => 'untouched']]);

    expect($address->fresh()->metadata)->toBe(['note' => 'untouched']);
    expect($address->fresh()->country_id)->toBeNull();
});

it('deletes pivots through model events and detaches snapshots on delete', function (): void {
    $host = new class extends Model {
        use HasAddresses;

        protected $table = 'test_owners';
    };
    $host->save();

    $address = Address::create(['line1' => 'Doomed', 'country_code' => 'MY']);
    $host->attachAddress($address, type: 'primary', label: 'Home');

    $snapshotable = new class extends Model {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $snapshot = AddressSnapshot::query()->findOrFail(app(AddressSnapshotWriter::class)->execute($snapshotable->getMorphClass(), $snapshotable->getKey(), (string) $address->getKey()));

    $pivotDeletingFired = false;
    Event::listen('eloquent.deleting: ' . Addressable::class, function () use (&$pivotDeletingFired): void {
        $pivotDeletingFired = true;
    });

    $address->delete();

    expect($pivotDeletingFired)->toBeTrue();
    expect(Addressable::query()->where('address_id', $address->getKey())->exists())->toBeFalse();
    expect($snapshot->fresh()->address_id)->toBeNull();
    expect($snapshot->fresh()->line1)->toBe('Doomed');
});

it('rolls back address reference cleanup when a later deleting observer vetoes deletion', function (): void {
    $host = new class extends Model {
        use HasAddresses;

        protected $table = 'test_owners';
    };
    $host->save();

    $address = Address::query()->create(['line1' => 'Preserved', 'country_code' => 'MY']);
    $attachment = $host->attachAddress($address);
    $snapshot = AddressSnapshot::query()->findOrFail(app(AddressSnapshotWriter::class)->execute($host->getMorphClass(), $host->getKey(), (string) $address->getKey()));

    $dispatcher = Address::getEventDispatcher();
    $temporaryDispatcher = clone $dispatcher;
    $temporaryDispatcher->listen('eloquent.deleting: ' . Address::class, static fn (): bool => false);
    Address::setEventDispatcher($temporaryDispatcher);

    try {
        expect($address->delete())->toBeFalse()
            ->and(Address::query()->whereKey($address->getKey())->exists())->toBeTrue()
            ->and(Addressable::query()->whereKey($attachment->getKey())->exists())->toBeTrue()
            ->and($snapshot->fresh()->address_id)->toBe($address->getKey());
    } finally {
        Address::setEventDispatcher($dispatcher);
    }
});
