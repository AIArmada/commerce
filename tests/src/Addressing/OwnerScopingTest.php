<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\CreateAddressSnapshotAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AddressingOwnerTestHost extends Model
{
    use HasAddresses;
    use HasUuids;

    protected $fillable = ['name'];

    public function getTable(): string
    {
        return 'addressing_test_hosts';
    }
}

beforeEach(function (): void {
    Schema::dropIfExists('addressing_test_hosts');
    Schema::create('addressing_test_hosts', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('adds owner columns to the address instance tables', function (): void {
    expect(Schema::hasColumn('addresses', 'owner_type'))->toBeTrue()
        ->and(Schema::hasColumn('addresses', 'owner_id'))->toBeTrue()
        ->and(Schema::hasColumn('addressables', 'owner_type'))->toBeTrue()
        ->and(Schema::hasColumn('addressables', 'owner_id'))->toBeTrue()
        ->and(Schema::hasColumn('address_snapshots', 'owner_type'))->toBeTrue()
        ->and(Schema::hasColumn('address_snapshots', 'owner_id'))->toBeTrue()
        ->and(Schema::hasIndex('addresses', 'addresses_owner_type_owner_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('addressables', 'addressables_owner_type_owner_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('address_snapshots', 'address_snapshots_owner_type_owner_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('address_snapshots', 'address_snapshots_snapshotable_type_snapshotable_id_index'))->toBeTrue();
});

it('isolates addresses, pivots, and snapshots across owners', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$hostA, $addressA] = OwnerContext::withOwner($ownerA, function (): array {
        $host = AddressingOwnerTestHost::query()->create(['name' => 'Host A']);
        $address = Address::query()->create(['line1' => 'Owner A', 'country_code' => 'MY']);
        $host->attachAddress($address, type: 'shipping', isPrimary: true);

        return [$host, $address];
    });

    [$hostB, $addressB] = OwnerContext::withOwner($ownerB, function (): array {
        $host = AddressingOwnerTestHost::query()->create(['name' => 'Host B']);
        $address = Address::query()->create(['line1' => 'Owner B', 'country_code' => 'MY']);
        $host->attachAddress($address, type: 'shipping', isPrimary: true);

        return [$host, $address];
    });

    expect(OwnerContext::withOwner($ownerA, fn (): array => Address::query()->pluck('id')->all()))
        ->toEqual([$addressA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => Address::query()->pluck('id')->all()))
        ->toEqual([$addressB->id])
        ->and(OwnerContext::withOwner($ownerA, fn (): int => $hostA->addresses()->count()))
        ->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => $hostA->addresses()->count()))
        ->toBe(0)
        ->and(OwnerContext::withOwner($ownerA, fn (): int => $hostB->addresses()->count()))
        ->toBe(0);

    $snapshot = OwnerContext::withOwner($ownerA, function () use ($hostA): AddressSnapshot {
        return app(CreateAddressSnapshotAction::class)->execute(
            snapshotable: $hostA,
            address: AddressData::from(['line1' => 'Owner A snapshot', 'countryCode' => 'MY']),
            reason: 'test',
        );
    });

    expect(OwnerContext::withOwner($ownerA, fn (): bool => AddressSnapshot::query()->whereKey($snapshot->id)->exists()))
        ->toBeTrue()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => AddressSnapshot::query()->whereKey($snapshot->id)->exists()))
        ->toBeFalse();
});

it('blocks cross-owner address attachments and global access without explicit context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $hostA = OwnerContext::withOwner($ownerA, fn (): AddressingOwnerTestHost => AddressingOwnerTestHost::query()->create(['name' => 'Host A']));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B',
        'country_code' => 'MY',
    ]));

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => $hostA->attachAddress($addressB)))
        ->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner($ownerA, function () use ($hostA, $addressB): void {
        $hostA->addresses()->attach($addressB->id, [
            'id' => (string) Str::orderedUuid(),
            'type' => 'shipping',
            'is_primary' => true,
        ]);
    }))->toThrow(AuthorizationException::class);

    $globalAddress = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global',
        'country_code' => 'MY',
    ]));

    expect(OwnerContext::withOwner($ownerA, fn (): bool => Address::query()->whereKey($globalAddress->id)->exists()))
        ->toBeFalse()
        ->and(OwnerContext::withOwner(null, fn (): bool => Address::query()->whereKey($globalAddress->id)->exists()))
        ->toBeTrue();
});
