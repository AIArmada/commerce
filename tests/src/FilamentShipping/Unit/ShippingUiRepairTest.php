<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Actions\PrintLabelAction;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Support\MoneyInput;
use AIArmada\FilamentShipping\Support\ShippingStatsAggregator;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\States\Delivered;
use AIArmada\Shipping\States\ExceptionStatus;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\Shipped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

uses(TestCase::class);

describe('Money input conversion', function (): void {
    it('converts major units to minor units without float truncation', function (): void {
        expect(MoneyInput::toMinor(19.99))->toBe(1999)
            ->and(MoneyInput::toMinor('19.99'))->toBe(1999)
            ->and(MoneyInput::toMinor(0.1 + 0.2))->toBe(30)
            ->and(MoneyInput::toMinor(0))->toBe(0)
            ->and(MoneyInput::toMinor(null))->toBeNull()
            ->and(MoneyInput::toMinor(''))->toBeNull();
    });
});

describe('Shipping stats aggregation', function (): void {
    it('computes the total from a single set of counts', function (): void {
        Shipment::query()->create([
            'reference' => 'STATS-PENDING',
            'carrier_code' => 'jnt',
            'status' => Pending::class,
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
        ]);

        Shipment::query()->create([
            'reference' => 'STATS-SHIPPED',
            'carrier_code' => 'jnt',
            'status' => Shipped::class,
            'shipped_at' => Carbon::now(),
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
        ]);

        Shipment::query()->create([
            'reference' => 'STATS-DELIVERED',
            'carrier_code' => 'jnt',
            'status' => Delivered::class,
            'delivered_at' => Carbon::now(),
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
        ]);

        Shipment::query()->create([
            'reference' => 'STATS-EXCEPTION',
            'carrier_code' => 'jnt',
            'status' => ExceptionStatus::class,
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $stats = app(ShippingStatsAggregator::class)->getAllStats();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        expect($stats['pending'])->toBe(1)
            ->and($stats['inTransit'])->toBe(1)
            ->and($stats['deliveredToday'])->toBe(1)
            ->and($stats['exceptions'])->toBe(1)
            ->and($stats['total'])->toBe(4)
            ->and($queries)->toHaveCount(5);
    });
});

describe('Manifest bulk pickup', function (): void {
    it('marks filtered shipments as picked up without loading them all at once', function (): void {
        $user = User::query()->create([
            'name' => 'Manifest User',
            'email' => 'manifest-user@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
        Gate::before(static fn (): bool => true);

        $today = Carbon::today()->toDateString();

        foreach (['PICK-1', 'PICK-2', 'PICK-3'] as $reference) {
            Shipment::query()->create([
                'reference' => $reference,
                'carrier_code' => 'jnt',
                'status' => Shipped::class,
                'shipped_at' => Carbon::parse($today)->startOfDay(),
                'origin_address' => ['country' => 'MY'],
                'destination_address' => ['country' => 'MY'],
            ]);
        }

        Shipment::query()->create([
            'reference' => 'PICK-DONE',
            'carrier_code' => 'jnt',
            'status' => Shipped::class,
            'shipped_at' => Carbon::parse($today)->startOfDay(),
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
            'metadata' => ['picked_up' => true],
        ]);

        $page = new ManifestPage;
        $page->mount();

        $method = new ReflectionMethod(ManifestPage::class, 'markFilteredShipmentsPickedUp');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBe(3)
            ->and(Shipment::query()->where('reference', 'PICK-1')->firstOrFail()->metadata['picked_up'])->toBeTrue()
            ->and(Shipment::query()->where('reference', 'PICK-2')->firstOrFail()->metadata['picked_up'])->toBeTrue()
            ->and(Shipment::query()->where('reference', 'PICK-3')->firstOrFail()->metadata['picked_up'])->toBeTrue();
    });
});

describe('Bulk label failures', function (): void {
    it('reports a generic message instead of driver exception details', function (): void {
        $user = User::query()->create([
            'name' => 'Label User',
            'email' => 'label-user@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
        Gate::before(static fn (): bool => true);

        $shipment = Shipment::query()->create([
            'reference' => 'LABEL-FAIL',
            'carrier_code' => 'no-such-driver',
            'tracking_number' => 'TRACK-FAIL-1',
            'status' => Shipped::class,
            'origin_address' => ['country' => 'MY'],
            'destination_address' => ['country' => 'MY'],
        ]);

        $bulkAction = PrintLabelAction::bulkAction();
        $property = new ReflectionProperty($bulkAction, 'action');
        $property->setAccessible(true);
        $closure = $property->getValue($bulkAction);

        expect($closure)->toBeInstanceOf(Closure::class);

        $livewire = Mockery::mock(Component::class);
        $closure(new Collection([$shipment]), $livewire);

        $notifications = collect(session('filament.notifications', []));
        $bodies = $notifications->pluck('body')->filter()->implode("\n");

        expect($bodies)->toContain('label generation failed')
            ->and($bodies)->not->toContain('no-such-driver');
    });
});
