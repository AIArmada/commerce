<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\Venue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('venue reads the canonical primary address relation', function (): void {
    $venue = Venue::factory()->create();

    $address = Address::create([
        'line1' => '123 Jalan Ampang',
        'line2' => 'Level 10',
        'city' => 'Kuala Lumpur',
        'state' => 'Wilayah Persekutuan',
        'postcode' => '50450',
        'country_code' => 'MY',
        'country' => 'Malaysia',
    ]);

    $venue->attachAddress($address, type: 'primary', isPrimary: true);

    $addressData = $venue->primaryAddress();

    expect($addressData?->line1)->toBe('123 Jalan Ampang')
        ->and($addressData?->line2)->toBe('Level 10')
        ->and($addressData?->city)->toBe('Kuala Lumpur')
        ->and($addressData?->country_code)->toBe('MY')
        ->and(DB::table('addressables')->where('address_id', $address->id)->value('owner_id'))
        ->toBe(OwnerContext::resolve()?->getKey());
});

test('venue uses the configured addressables table throughout the shared address relation', function (): void {
    $pivotTable = 'events_custom_addressables';
    $originalPivotTable = config('addressing.database.tables.addressables');

    try {
        config()->set('addressing.database.tables.addressables', $pivotTable);
        Schema::create($pivotTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('address_id')->index();
            $table->uuidMorphs('addressable');
            $table->string('type')->default('primary')->index();
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
        });

        $venue = Venue::factory()->create();
        $address = Address::create([
            'line1' => '123 Jalan Ampang',
            'city' => 'Kuala Lumpur',
            'country_code' => 'MY',
        ]);

        $venue->addresses()->attach($address->id, [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);

        expect($venue->primaryAddress()?->line1)->toBe('123 Jalan Ampang')
            ->and(DB::table($pivotTable)->where('address_id', $address->id)->value('is_primary'))
            ->toBe(1);
    } finally {
        Schema::dropIfExists($pivotTable);
        config()->set('addressing.database.tables.addressables', $originalPivotTable);
    }
});

test('event locations keep shared addresses isolated by their event owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $eventA = OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create());
    $locationA = OwnerContext::withOwner($ownerA, fn (): EventLocation => EventLocation::factory()->create([
        'event_id' => $eventA->id,
    ]));
    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A location',
        'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B location',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($locationA, $addressA): void {
        $locationA->addresses()->attach($addressA->id, [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);
    });

    expect(OwnerContext::withOwner($ownerA, fn (): int => $locationA->addresses()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => $locationA->addresses()->count()))->toBe(0);

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($locationA, $addressB): void {
        $locationA->addresses()->attach($addressB->id, [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);
    }))->toThrow(AuthorizationException::class);
});
