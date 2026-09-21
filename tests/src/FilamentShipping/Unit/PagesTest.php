<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Pages\ShippingDashboard;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\States\Shipped;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

uses(TestCase::class);

// ============================================
// Filament Shipping Pages Tests
// ============================================

describe('ShippingDashboard', function (): void {
    it('returns title and widgets', function (): void {
        $page = new ShippingDashboard;

        $getHeaderWidgets = new ReflectionMethod($page, 'getHeaderWidgets');
        $getFooterWidgets = new ReflectionMethod($page, 'getFooterWidgets');

        /** @var array $headerWidgets */
        $headerWidgets = $getHeaderWidgets->invoke($page);
        /** @var array $footerWidgets */
        $footerWidgets = $getFooterWidgets->invoke($page);

        expect($headerWidgets)->not()->toBeEmpty();
        expect($footerWidgets)->not()->toBeEmpty();
    });
});

describe('ManifestPage', function (): void {
    it('mounts with today\'s manifest date', function (): void {
        $page = new ManifestPage;
        $page->mount();

        expect($page->manifestDate)->toBe(Carbon::today()->toDateString());
    });

    it('builds manifest form schema and table definition', function (): void {
        $page = new ManifestPage;
        $page->mount();

        $schema = $page->form(Schema::make());
        expect($schema->getComponents())->not()->toBeEmpty();

        $table = $page->table(Table::make($page));
        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });

    it('filters manifest table query by carrier and date', function (): void {
        $page = new ManifestPage;
        $page->mount();

        $date = Carbon::today()->toDateString();

        Shipment::query()->create([
            'owner_type' => null,
            'owner_id' => null,
            'reference' => 'M-REF-1',
            'carrier_code' => 'jnt',
            'status' => Shipped::class,
            'shipped_at' => Carbon::parse($date)->startOfDay(),
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        Shipment::query()->create([
            'owner_type' => null,
            'owner_id' => null,
            'reference' => 'M-REF-2',
            'carrier_code' => 'flat_rate',
            'status' => Shipped::class,
            'shipped_at' => Carbon::parse($date)->startOfDay(),
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        $page->manifestDate = $date;
        $page->selectedCarrier = 'jnt';

        $method = new ReflectionMethod($page, 'getTableQuery');

        /** @var Builder $query */
        $query = $method->invoke($page);

        expect($query->count())->toBe(1);
    });

    it('defines header actions', function (): void {
        $page = new ManifestPage;

        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array $actions */
        $actions = $method->invoke($page);

        expect($actions)->not()->toBeEmpty();
    });
});
