<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\CreateAddressSnapshotAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Traits\HasAddresses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
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
    $host = new class extends Model
    {
        use HasAddresses;

        protected $table = 'test_owners';
    };
    $host->save();

    $address = Address::create(['line1' => 'Doomed', 'country_code' => 'MY']);
    $host->attachAddress($address, type: 'primary', label: 'Home');

    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $snapshot = app(CreateAddressSnapshotAction::class)->execute($snapshotable, $address);

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
