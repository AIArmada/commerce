<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Pages\ShippingDashboard;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\States\Shipped;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

uses(TestCase::class);

// ============================================
// Filament Shipping Pages Tests
// ============================================

describe('ShippingDashboard', function (): void {
    it('has correct navigation icon', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'navigationIcon');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(Heroicon::OutlinedChartBar);
    });

    it('has correct navigation group', function (): void {
        expect(ShippingDashboard::getNavigationGroup())->toBe('Shipping');
    });

    it('has correct navigation label', function (): void {
        expect(ShippingDashboard::getNavigationLabel())->toBe('Dashboard');
    });

    it('has navigation sort order of 0', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'navigationSort');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(0);
    });

    it('has correct slug', function (): void {
        $reflection = new ReflectionProperty(ShippingDashboard::class, 'slug');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('shipping-dashboard');
    });

    it('returns title and widgets', function (): void {
        $page = new ShippingDashboard;

        expect($page->getTitle())->toBe('Shipping Dashboard');
        expect($page->getHeaderWidgetsColumns())->toBe(5);

        $getHeaderWidgets = new ReflectionMethod($page, 'getHeaderWidgets');
        $getHeaderWidgets->setAccessible(true);
        $getFooterWidgets = new ReflectionMethod($page, 'getFooterWidgets');
        $getFooterWidgets->setAccessible(true);

        /** @var array $headerWidgets */
        $headerWidgets = $getHeaderWidgets->invoke($page);
        /** @var array $footerWidgets */
        $footerWidgets = $getFooterWidgets->invoke($page);

        expect($headerWidgets)->not()->toBeEmpty();
        expect($footerWidgets)->not()->toBeEmpty();
    });
});

describe('ManifestPage', function (): void {
    it('has correct navigation icon', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'navigationIcon');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(Heroicon::OutlinedDocumentText);
    });

    it('has correct navigation group', function (): void {
        expect(ManifestPage::getNavigationGroup())->toBe('Shipping');
    });

    it('has correct navigation label', function (): void {
        expect(ManifestPage::getNavigationLabel())->toBe('Manifests');
    });

    it('has navigation sort order of 5', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'navigationSort');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe(5);
    });

    it('has correct slug', function (): void {
        $reflection = new ReflectionProperty(ManifestPage::class, 'slug');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('shipping-manifests');
    });

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
        $method->setAccessible(true);

        /** @var Builder $query */
        $query = $method->invoke($page);

        expect($query->count())->toBe(1);
    });

    it('defines header actions', function (): void {
        $page = new ManifestPage;

        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        /** @var array $actions */
        $actions = $method->invoke($page);

        expect($actions)->not()->toBeEmpty();
    });
});
