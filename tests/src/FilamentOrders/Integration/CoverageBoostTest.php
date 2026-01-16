<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentOrders\FilamentOrdersPlugin;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\FilamentOrders\Resources\OrderResource\Pages\EditOrder;
use AIArmada\FilamentOrders\Resources\OrderResource\Pages\ListOrders;
use AIArmada\FilamentOrders\Resources\OrderResource\Pages\ViewOrder;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers\NotesRelationManager;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers\RefundsRelationManager;
use AIArmada\FilamentOrders\Widgets\RecentOrdersWidget;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

function makeFilamentOrdersTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds OrderResource form, table, and infolist schemas', function (): void {
    $formSchema = OrderResource::form(Schema::make());
    expect($formSchema)->toBeInstanceOf(Schema::class);

    $table = OrderResource::table(makeFilamentOrdersTable());
    expect($table)->toBeInstanceOf(Table::class);

    $infolist = OrderResource::infolist(Schema::make());
    expect($infolist)->toBeInstanceOf(Schema::class);

    expect(OrderResource::getPages())
        ->toHaveKeys(['index', 'create', 'view', 'edit']);

    expect(OrderResource::getRelations())
        ->toContain(ItemsRelationManager::class, PaymentsRelationManager::class, RefundsRelationManager::class, NotesRelationManager::class);
});

it('builds relation manager schemas and tables', function (): void {
    $items = app(ItemsRelationManager::class);
    $payments = app(PaymentsRelationManager::class);
    $refunds = app(RefundsRelationManager::class);
    $notes = app(NotesRelationManager::class);

    expect($items->table(makeFilamentOrdersTable()))->toBeInstanceOf(Table::class);
    expect($payments->table(makeFilamentOrdersTable()))->toBeInstanceOf(Table::class);
    expect($refunds->table(makeFilamentOrdersTable()))->toBeInstanceOf(Table::class);

    expect($notes->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($notes->table(makeFilamentOrdersTable()))->toBeInstanceOf(Table::class);
});

it('builds resource pages header actions', function (): void {
    $list = new class extends ListOrders
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $edit = new class extends EditOrder
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $view = new class extends ViewOrder
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    expect($list->headerActions())->toBeArray()->not->toBeEmpty();
    expect($edit->headerActions())->toBeArray()->not->toBeEmpty();
    expect($view->headerActions())->toBeArray()->not->toBeEmpty();
});

it('registers the filament orders plugin without errors', function (): void {
    $panel = Panel::make()->id('admin');

    $plugin = FilamentOrdersPlugin::make();

    expect($plugin->getId())->toBe('filament-orders');

    $plugin->register($panel);
    $plugin->boot($panel);
});

it('builds the recent orders widget table', function (): void {
    $widget = app(RecentOrdersWidget::class);

    $table = $widget->table(makeFilamentOrdersTable());

    expect($table)->toBeInstanceOf(Table::class);
});
