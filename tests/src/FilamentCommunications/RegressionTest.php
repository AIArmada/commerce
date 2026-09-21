<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Filament\Communications\FilamentCommunicationsPlugin;
use AIArmada\Filament\Communications\RelationManagers\CommunicationsRelationManager;
use AIArmada\Filament\Communications\RelationManagers\CommunicationTimelineRelationManager;
use AIArmada\Filament\Communications\RelationManagers\DeliveriesRelationManager;
use AIArmada\Filament\Communications\Resources\CommunicationBatchResource;
use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource;
use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource;
use AIArmada\Filament\Communications\Resources\CommunicationResource;
use AIArmada\Filament\Communications\Resources\CommunicationResource\Pages\ViewCommunication;
use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource;
use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource;
use AIArmada\Filament\Communications\Resources\CommunicationThreadResource;
use AIArmada\Filament\Communications\Support\CommunicationFilterOptions;
use AIArmada\Filament\Communications\Widgets\DeliveryStatusOverviewWidget;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class CommunicationsRepairTableHost extends Component implements HasTable
{
    use InteractsWithTable;

    public function getTable(): Table
    {
        return Table::make($this);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function makeRepairDelivery(DeliveryStatus $status, int $attemptCount = 0, int $maxAttempts = 3): CommunicationDelivery
{
    $communication = (new Communication)->forceFill([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'repair-test',
        'status' => CommunicationStatus::Draft,
    ]);
    $communication->save();

    $recipient = CommunicationRecipient::query()->create([
        'communication_id' => $communication->getKey(),
        'role' => 'to',
    ]);

    $delivery = (new CommunicationDelivery)->forceFill([
        'communication_id' => $communication->getKey(),
        'recipient_id' => $recipient->getKey(),
        'channel' => 'email',
        'provider' => 'ses',
        'status' => $status,
        'attempt_count' => $attemptCount,
        'max_attempts' => $maxAttempts,
    ]);
    $delivery->save();

    return $delivery;
}

function callRetryAction(CommunicationDelivery $delivery): mixed
{
    $livewire = new CommunicationsRepairTableHost;
    $table = CommunicationDeliveryResource::table(Table::make($livewire));

    $action = $table->getAction('retry');
    $action->livewire($livewire);
    $action->record($delivery);

    return $action->call();
}

describe('retry race handling', function (): void {
    beforeEach(function (): void {
        $user = User::query()->create([
            'name' => 'Comms Repair',
            'email' => 'comms-repair-' . uniqid() . '@example.com',
            'password' => 'password',
        ]);

        test()->actingAs($user);
    });

    test('retry on a non-failed delivery notifies instead of throwing', function (): void {
        $delivery = makeRepairDelivery(DeliveryStatus::Sent);

        $thrown = null;

        try {
            callRetryAction($delivery);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeNull();

        Notification::assertNotified('Delivery retry failed');
    });

    test('retry with exhausted attempts notifies instead of throwing', function (): void {
        $delivery = makeRepairDelivery(DeliveryStatus::Failed, 3, 3);

        $thrown = null;

        try {
            callRetryAction($delivery);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeNull();

        Notification::assertNotified('Delivery retry failed');
    });
});

describe('widget status buckets', function (): void {
    test('every delivery status is counted exactly once', function (): void {
        foreach (DeliveryStatus::cases() as $status) {
            makeRepairDelivery($status);
        }

        $widget = app(DeliveryStatusOverviewWidget::class);
        $method = new ReflectionMethod($widget, 'getStats');
        $stats = $method->invoke($widget);

        expect($stats)->toHaveCount(5);

        $values = array_map(
            static fn ($stat): int => (int) $stat->getValue(),
            $stats,
        );

        expect(array_sum($values))->toBe(count(DeliveryStatus::cases()))
            ->and($values[0])->toBe(4, 'Pending bucket: pending, scheduled, queued, sending')
            ->and($values[1])->toBe(3, 'Sent bucket: sent, accepted, received')
            ->and($values[2])->toBe(5, 'Delivered bucket: delivered, opened, read, clicked, replied')
            ->and($values[3])->toBe(4, 'Failed bucket: failed, bounced, complained, expired')
            ->and($values[4])->toBe(3, 'Suppressed bucket: suppressed, unsubscribed, cancelled');
    });
});

describe('relation manager wiring', function (): void {
    test('communication resource exposes deliveries and timeline managers', function (): void {
        expect(CommunicationResource::getRelations())->toBe([
            DeliveriesRelationManager::class,
            CommunicationTimelineRelationManager::class,
        ]);
    });

    test('thread resource exposes communications manager', function (): void {
        expect(CommunicationThreadResource::getRelations())->toBe([
            CommunicationsRelationManager::class,
        ]);
    });
});

describe('view page infolist', function (): void {
    test('view page inherits the resource infolist without overriding it', function (): void {
        $method = new ReflectionMethod(ViewCommunication::class, 'infolist');

        expect($method->getDeclaringClass()->getName())->toBe(ViewRecord::class);
    });
});

describe('navigation sort offsets', function (): void {
    test('offsets pin a deterministic order while defaulting to the base sort', function (): void {
        config()->set('filament-communications.navigation.sort', 80);

        expect(CommunicationResource::getNavigationSort())->toBe(80)
            ->and(CommunicationDeliveryResource::getNavigationSort())->toBe(80);

        config()->set('filament-communications.navigation.offsets.deliveries', 1);
        config()->set('filament-communications.navigation.offsets.threads', 2);

        expect(CommunicationResource::getNavigationSort())->toBe(80)
            ->and(CommunicationDeliveryResource::getNavigationSort())->toBe(81)
            ->and(CommunicationThreadResource::getNavigationSort())->toBe(82);
    });
});

describe('shared filter options', function (): void {
    test('all channel filters share the same options', function (string $resourceClass): void {
        $table = $resourceClass::table(Table::make(Mockery::mock(HasTable::class)));

        /** @phpstan-ignore argument.templateType */
        expect($table->getFilter('channel')?->getOptions())->toBe(CommunicationFilterOptions::channels());
    })->with([
        CommunicationDeliveryResource::class,
        CommunicationThreadResource::class,
        CommunicationPreferenceResource::class,
        CommunicationSuppressionResource::class,
    ]);

    test('delivery provider filter shares the same options', function (): void {
        $table = CommunicationDeliveryResource::table(Table::make(Mockery::mock(HasTable::class)));

        expect($table->getFilter('provider')?->getOptions())->toBe(CommunicationFilterOptions::providers());
    });
});

describe('widget caching', function (): void {
    test('totals are cached per render window', function (): void {
        makeRepairDelivery(DeliveryStatus::Pending);

        $widget = app(DeliveryStatusOverviewWidget::class);
        $method = new ReflectionMethod($widget, 'getStats');

        /** @var array<int, mixed> $first */
        $first = $method->invoke($widget);
        expect((int) $first[0]->getValue())->toBe(1);

        makeRepairDelivery(DeliveryStatus::Pending);

        /** @var array<int, mixed> $second */
        $second = $method->invoke($widget);
        expect((int) $second[0]->getValue())->toBe(1, 'second render within TTL must reuse the cached totals');
    });

    test('widget polls at the cache TTL interval', function (): void {
        $property = new ReflectionProperty(DeliveryStatusOverviewWidget::class, 'pollingInterval');

        expect($property->getValue(app(DeliveryStatusOverviewWidget::class)))->toBe('60s');
    });
});

describe('widget toggle', function (): void {
    test('delivery overview widget is registered by default', function (): void {
        $panel = Panel::make();

        FilamentCommunicationsPlugin::make()->register($panel);

        expect($panel->getWidgets())->toContain(DeliveryStatusOverviewWidget::class);
    });

    test('delivery overview widget can be disabled via config', function (): void {
        config()->set('filament-communications.widgets.delivery_overview.enabled', false);

        $panel = Panel::make();

        FilamentCommunicationsPlugin::make()->register($panel);

        expect($panel->getWidgets())->not->toContain(DeliveryStatusOverviewWidget::class);
    });
});

describe('resource registry sanity', function (): void {
    test('all seven resources stay registered by default', function (): void {
        $panel = Panel::make();

        FilamentCommunicationsPlugin::make()->register($panel);

        expect($panel->getResources())->toContain(
            CommunicationResource::class,
            CommunicationDeliveryResource::class,
            CommunicationThreadResource::class,
            CommunicationTemplateResource::class,
            CommunicationPreferenceResource::class,
            CommunicationSuppressionResource::class,
            CommunicationBatchResource::class,
        );
    });
});
