<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\FilamentJnt\Actions\CancelOrderAction;
use AIArmada\FilamentJnt\Actions\SyncTrackingAction;
use AIArmada\FilamentJnt\Resources\BaseJntResource;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Pages\ListJntOrders;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Pages\ViewJntOrder;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Schemas\JntOrderInfolist;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Tables\JntOrderTable;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ListJntTrackingEvents;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ViewJntTrackingEvent;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Schemas\JntTrackingEventInfolist;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables\JntTrackingEventTable;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ListJntWebhookLogs;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ViewJntWebhookLog;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Schemas\JntWebhookLogInfolist;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Tables\JntWebhookLogTable;
use AIArmada\FilamentJnt\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

uses(FilamentJntTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentJnt_makeSchemaLivewire')) {
    function filamentJnt_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

function makeFilamentJntTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds Filament JNT resources, schemas, tables, pages, widgets, and actions', function (): void {
    $schemaLivewire = filamentJnt_makeSchemaLivewire();

    foreach ([
        JntOrderResource::class,
        JntTrackingEventResource::class,
        JntWebhookLogResource::class,
    ] as $resource) {
        expect($resource::table(makeFilamentJntTable()))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->toBeArray();
        expect($resource::getGloballySearchableAttributes())->toBeArray();
        expect($resource::getNavigationBadgeColor())->toBeString();
        expect($resource::getNavigationBadge())->toBeNull();
    }

    // Resource builders
    expect(JntOrderTable::configure(makeFilamentJntTable()))->toBeInstanceOf(Table::class);
    expect(JntOrderInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(JntTrackingEventTable::configure(makeFilamentJntTable()))->toBeInstanceOf(Table::class);
    expect(JntTrackingEventInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(JntWebhookLogTable::configure(makeFilamentJntTable()))->toBeInstanceOf(Table::class);
    expect(JntWebhookLogInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    // List pages: titles & read-only header actions.
    foreach ([ListJntOrders::class, ListJntTrackingEvents::class, ListJntWebhookLogs::class] as $pageClass) {
        $page = app($pageClass);

        expect($page->getTitle())->toBeString();
        expect($page->getSubheading())->toBeString();

        $method = new ReflectionMethod(ReadOnlyListRecords::class, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBe([]);
    }

    // View pages: header actions via reflection.
    foreach ([ViewJntOrder::class, ViewJntTrackingEvent::class, ViewJntWebhookLog::class] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    // Widget
    expect(app(JntStatsWidget::class))->toBeInstanceOf(JntStatsWidget::class);

    // Actions
    expect(SyncTrackingAction::make())->toBeInstanceOf(Action::class);
    expect(CancelOrderAction::make())->toBeInstanceOf(Action::class);

    // Base resource helpers
    expect(is_a(BaseJntResource::class, Filament\Resources\Resource::class, true))->toBeTrue();
});
