<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Data\EventAddressData;
use AIArmada\Events\Data\OccurrenceDetailData;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('stores custom polymorphic addresses and shared sub-locations on occurrences', function (): void {
    if (! Schema::hasTable('event_test_addresses')) {
        Schema::create('event_test_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('line1')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('MY');
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
    }

    $series = EventSeries::query()->create([
        'name' => 'Address Series',
        'slug' => 'address-series',
    ]);

    $event = EventModel::query()->create([
        'event_series_id' => $series->id,
        'name' => 'Polymorphic Address Event',
        'slug' => 'polymorphic-address-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $address = TestEventAddress::query()->create([
        'name' => 'Al-Nur Mosque',
        'line1' => 'Lot 12, Jalan Example',
        'city' => 'Kuala Lumpur',
        'country' => 'MY',
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $subLocation = OwnerContext::withOwner(null, function (): EventSubLocation {
        return EventSubLocation::query()->create([
            'name' => 'Main Prayer Hall',
            'slug' => 'main-prayer-hall',
            'description' => 'Central hall for the event.',
            'order_column' => 1,
        ]);
    });

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => $series->name,
            'slug' => $series->slug,
        ],
        event: [
            'name' => $event->name,
            'slug' => $event->slug,
            'default_timezone' => $event->default_timezone,
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
        address: $address,
        sub_location: [
            'slug' => $subLocation->slug,
            'name' => $subLocation->name,
            'description' => $subLocation->description,
            'order_column' => $subLocation->order_column,
        ],
    );

    $loadedOccurrence = OwnerContext::withOwner(null, static fn (): Occurrence => $occurrence->fresh(['address', 'subLocation']) ?? $occurrence);
    $detail = OccurrenceDetailData::fromOccurrence($loadedOccurrence);

    expect($loadedOccurrence->address)->toBeInstanceOf(TestEventAddress::class)
        ->and($loadedOccurrence->address?->getKey())->toBe($address->getKey())
        ->and($loadedOccurrence->subLocation)->toBeInstanceOf(EventSubLocation::class)
        ->and($loadedOccurrence->subLocation?->slug)->toBe('main-prayer-hall')
        ->and($loadedOccurrence->locationLabel())->toBe('Main Prayer Hall, Al-Nur Mosque')
        ->and($detail->addressType)->toBe(TestEventAddress::class)
        ->and($detail->addressId)->toBe($address->getKey())
        ->and($detail->addressLabel)->toBe('Al-Nur Mosque')
        ->and($detail->addressLines)->toBe([
            'Lot 12, Jalan Example',
            'Kuala Lumpur',
            'MY',
        ])
        ->and($detail->subLocationName)->toBe('Main Prayer Hall')
        ->and($detail->locationLabel)->toBe('Main Prayer Hall, Al-Nur Mosque');

    $arrayAddressOccurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => $series->name,
            'slug' => $series->slug,
        ],
        event: [
            'name' => $event->name,
            'slug' => $event->slug,
            'default_timezone' => $event->default_timezone,
        ],
        occurrence: [
            'starts_at' => '2026-06-06 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
        address: [
            'address_type' => TestEventAddress::class,
            'address_id' => $address->getKey(),
        ],
        sub_location: 'meeting-room',
    );

    $loadedArrayAddressOccurrence = OwnerContext::withOwner(null, static fn (): Occurrence => $arrayAddressOccurrence->fresh(['address', 'subLocation']) ?? $arrayAddressOccurrence);

    expect($loadedArrayAddressOccurrence->address)->toBeInstanceOf(TestEventAddress::class)
        ->and($loadedArrayAddressOccurrence->subLocation?->slug)->toBe('meeting-room')
        ->and($loadedArrayAddressOccurrence->subLocation?->name)->toBe('Meeting Room')
        ->and($loadedArrayAddressOccurrence->locationLabel())->toBe('Meeting Room, Al-Nur Mosque')
        ->and(OwnerContext::withOwner(null, static fn (): int => Occurrence::query()->where('address_type', TestEventAddress::class)->count()))->toBe(2);
});

final class TestEventAddress extends Model implements EventAddressable
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'event_test_addresses';
    }

    public function eventAddressData(): EventAddressData
    {
        return new EventAddressData(
            label: $this->name,
            lines: array_values(array_filter([
                $this->line1,
                $this->city,
                $this->country,
            ])),
            timezone: $this->timezone,
            country: $this->country,
        );
    }
}
