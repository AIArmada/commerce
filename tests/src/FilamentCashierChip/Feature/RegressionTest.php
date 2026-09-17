<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\Invoices;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\PaymentMethods;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\Subscriptions;
use AIArmada\FilamentCashierChip\Jobs\SyncCustomersToChipJob;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages\ListCustomers;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers\SubscriptionsRelationManager;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables\CustomerTable;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\RelationManagers\SubscriptionItemsRelationManager;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Schemas\SubscriptionInfolist;
use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;
use AIArmada\FilamentCashierChip\Widgets\AttentionRequiredWidget;
use AIArmada\FilamentCashierChip\Widgets\ChurnRateWidget;
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentCashierChip\Widgets\SubscriptionDistributionWidget;
use AIArmada\FilamentCashierChip\Widgets\TrialConversionsWidget;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

beforeEach(function (): void {
    if (! SchemaFacade::hasTable('activity_log')) {
        SchemaFacade::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
});

afterEach(function (): void {
    Mockery::close();
    CustomerTable::resetGenericTrialQuerySupport();
});

function regression_bindOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function regression_createSubscription(?Model $owner, array $subscriptionAttributes = [], array $itemAttributes = []): Subscription
{
    $owner ??= new User([
        'name' => 'Regression Owner',
        'email' => 'repair-regression-' . Str::random(8) . '@example.com',
    ]);

    if (! $owner->exists) {
        $owner->save();
    }

    return OwnerContext::withOwner($owner, function () use ($owner, $subscriptionAttributes, $itemAttributes): Subscription {
        $subscription = new Subscription;
        $subscription->forceFill(array_merge([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'billable_type' => $owner->getMorphClass(),
            'billable_id' => (string) $owner->getKey(),
            'type' => 'default',
            'chip_id' => 'sub_' . Str::uuid()->toString(),
            'chip_status' => SubscriptionStatus::Active,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $subscriptionAttributes));
        $subscription->save();

        $item = new SubscriptionItem;
        $item->forceFill(array_merge([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'subscription_id' => $subscription->id,
            'chip_id' => 'item_' . Str::uuid()->toString(),
            'chip_price' => 'price_basic',
            'quantity' => 1,
            'unit_amount' => 10_00,
        ], $itemAttributes));
        $item->save();

        return $subscription;
    });
}

function regression_makeTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

function regression_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

/**
 * @return array<string, mixed>
 */
function regression_notificationTitles(): array
{
    return collect(session()->get('filament.notifications', []))->pluck('title')->all();
}

function regression_enableOwnerScoping(): void
{
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
}

it('bulk pause uses domain transitions, sets paused_at, and stays owner-scoped', function (): void {
    regression_enableOwnerScoping();

    $ownerA = User::query()->create(['name' => 'A', 'email' => 'repair-r1-1-a@example.com']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'repair-r1-1-b@example.com']);

    regression_bindOwner($ownerA);

    $activeA = regression_createSubscription($ownerA);
    $activeB = regression_createSubscription($ownerB);
    $pausedA = regression_createSubscription($ownerA, ['chip_status' => SubscriptionStatus::Paused]);

    $method = new ReflectionMethod(ListSubscriptions::class, 'transitionSubscriptionsInChunks');

    $result = $method->invoke(null, SubscriptionStatus::Active, fn (Subscription $subscription): mixed => $subscription->pause());

    expect($result)->toBe(['succeeded' => 1, 'failed' => 0]);
    expect($activeA->refresh()->chip_status)->toBe(SubscriptionStatus::Paused);
    expect($activeA->paused_at)->not->toBeNull();
    expect($activeB->refresh()->chip_status)->toBe(SubscriptionStatus::Active);
    expect($pausedA->refresh()->chip_status)->toBe(SubscriptionStatus::Paused);
});

it('bulk resume unpauses through the domain transition', function (): void {
    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-1-resume@example.com']);

    $paused = regression_createSubscription($owner, [
        'chip_status' => SubscriptionStatus::Paused,
        'paused_at' => now()->subDay(),
    ]);

    $method = new ReflectionMethod(ListSubscriptions::class, 'transitionSubscriptionsInChunks');

    $result = $method->invoke(null, SubscriptionStatus::Paused, fn (Subscription $subscription): mixed => $subscription->unpause());

    expect($result)->toBe(['succeeded' => 1, 'failed' => 0]);
    expect($paused->refresh()->chip_status)->toBe(SubscriptionStatus::Active);
    expect($paused->paused_at)->toBeNull();
});

it('customer resource query does not crash when owner scoping is enabled', function (): void {
    regression_enableOwnerScoping();

    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-2@example.com']);
    regression_bindOwner($owner);

    expect(CustomerResource::getEloquentQuery()->count())->toBeGreaterThanOrEqual(1);
});

it('missing owner key defaults to unscoped like the domain', function (): void {
    config()->offsetUnset('cashier-chip.features.owner.enabled');
    regression_bindOwner(null);

    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-19@example.com']);
    $subscription = regression_createSubscription($owner);

    expect(SubscriptionResource::getEloquentQuery()->whereKey($subscription->id)->exists())->toBeTrue();
});

it('portal exposes cancelled grace-period subscriptions for resume', function (): void {
    $user = User::query()->create(['name' => 'A', 'email' => 'repair-r1-3@example.com']);

    $cancelled = regression_createSubscription($user, [
        'chip_status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->addDays(3),
        'canceled_at' => now(),
    ]);

    $page = new class($user) extends Subscriptions
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $viewData = $page->getViewData();

    expect($viewData['cancelledSubscriptions']->pluck('id')->all())->toContain($cancelled->id);
    expect($viewData['subscriptions']->pluck('id')->all())->not->toContain($cancelled->id);
});

it('blade renders the Ending Soon resume section', function (): void {
    $blade = file_get_contents((string) realpath(__DIR__ . '/../../../../packages/filament-cashier-chip/resources/views/pages/subscriptions.blade.php'));

    expect($blade)->toContain('cancelledSubscriptions')
        ->and($blade)->toContain('Ending Soon')
        ->and($blade)->toContain('resumeSubscription');
});

it('payment summary resolves the default method once per record', function (): void {
    $paymentMethod = new class
    {
        public function brand(): string
        {
            return 'visa';
        }

        public function lastFour(): string
        {
            return '4242';
        }
    };

    $record = new class($paymentMethod) extends Model
    {
        public int $resolveCalls = 0;

        public function __construct(private readonly ?object $method = null)
        {
            parent::__construct();
        }

        public function defaultPaymentMethod(): ?object
        {
            $this->resolveCalls++;

            return $this->method;
        }
    };

    $method = new ReflectionMethod(CustomerTable::class, 'defaultPaymentMethodSummary');

    expect($method->invoke(null, $record))->toBe('Visa •••• 4242');
    expect($record->resolveCalls)->toBe(1);
});

it('customer table preloads subscription counts and billable relations', function (): void {
    $user = User::query()->create(['name' => 'A', 'email' => 'repair-r1-4@example.com']);
    regression_createSubscription($user);
    regression_createSubscription($user);

    $query = User::query();
    $method = new ReflectionMethod(CustomerTable::class, 'eagerLoadCustomerRelations');
    $method->invoke(null, $query);

    expect($query->getEagerLoads())->toHaveKeys(['chipCustomerLink', 'storedPaymentMethods']);

    $loaded = $query->first();

    expect($loaded->getAttribute('subscriptions_count'))->toBe(2);
});

it('mrr chart is cached per owner after the first computation', function (): void {
    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-5@example.com']);
    regression_createSubscription($owner, ['created_at' => now()->subMonths(2)], ['unit_amount' => 25_00]);

    $widget = app(MRRWidget::class);
    $method = new ReflectionMethod(MRRWidget::class, 'getMRRChart');

    DB::enableQueryLog();
    $first = $method->invoke($widget);
    $firstQueries = count(DB::getQueryLog());

    DB::flushQueryLog();
    $second = $method->invoke($widget);
    $secondQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first)->toBe($second);
    expect($firstQueries)->toBeGreaterThan(0);
    expect($secondQueries)->toBe(0);
});

it('distribution widget uses a single grouped query', function (): void {
    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-5-dist@example.com']);
    regression_createSubscription($owner);
    regression_createSubscription($owner, ['chip_status' => SubscriptionStatus::Trialing]);

    $widget = app(SubscriptionDistributionWidget::class);
    $method = new ReflectionMethod(SubscriptionDistributionWidget::class, 'getDistributionData');

    DB::enableQueryLog();
    $data = $method->invoke($widget);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1);
    expect($data['labels'])->toContain('Active', 'Trialing');
    expect(array_sum($data['counts']))->toBe(2);
});

it('sync action dispatches the queued job instead of syncing inline', function (): void {
    Queue::fake();

    $page = app(ListCustomers::class);
    $method = new ReflectionMethod(ListCustomers::class, 'getHeaderActions');
    $actions = $method->invoke($page);

    $sync = collect($actions)->firstWhere(fn (Action $action): bool => $action->getName() === 'sync_all_to_chip');

    expect($sync)->toBeInstanceOf(Action::class);

    $sync->call();

    Queue::assertPushed(SyncCustomersToChipJob::class, 1);
});

it('sync action refuses models without CHIP support', function (): void {
    Queue::fake();
    Cashier::useCustomerModel(Model::class);

    try {
        $page = app(ListCustomers::class);
        $method = new ReflectionMethod(ListCustomers::class, 'getHeaderActions');
        $actions = $method->invoke($page);

        $sync = collect($actions)->firstWhere(fn (Action $action): bool => $action->getName() === 'sync_all_to_chip');
        $sync->call();

        Queue::assertNotPushed(SyncCustomersToChipJob::class);
        expect(regression_notificationTitles())->toContain('Sync Unavailable');
    } finally {
        Cashier::useCustomerModel(User::class);
    }
});

it('sync job links unlinked customers and skips unsupported models', function (): void {
    $user = User::query()->create(['name' => 'A', 'email' => 'repair-r1-6@example.com']);

    (new SyncCustomersToChipJob)->handle();

    expect($user->refresh()->hasChipId())->toBeTrue();

    Cashier::useCustomerModel(Model::class);

    try {
        // Explicit try/catch: not->toThrow(Throwable::class) is vacuous on interfaces.
        $thrown = null;

        try {
            (new SyncCustomersToChipJob)->handle();
        } catch (Throwable $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeNull();
    } finally {
        Cashier::useCustomerModel(User::class);
    }
});

it('widgets hide when owner scoping is enabled without a resolved owner', function (): void {
    $widgets = [
        ActiveSubscribersWidget::class,
        MRRWidget::class,
        ChurnRateWidget::class,
        TrialConversionsWidget::class,
        AttentionRequiredWidget::class,
        RevenueChartWidget::class,
        SubscriptionDistributionWidget::class,
    ];

    foreach ($widgets as $widget) {
        expect($widget::canView())->toBeTrue();
    }

    regression_enableOwnerScoping();
    regression_bindOwner(null);

    foreach ($widgets as $widget) {
        expect($widget::canView())->toBeFalse();
    }

    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-7@example.com']);

    OwnerContext::withOwner($owner, function () use ($widgets): void {
        foreach ($widgets as $widget) {
            expect($widget::canView())->toBeTrue();
        }
    });
});

it('monthly normalization guards zero interval counts', function (): void {
    $widget = app(MRRWidget::class);
    $method = new ReflectionMethod(MRRWidget::class, 'normalizeToMonthly');

    expect($method->invoke($widget, 12_00, 'month', 0))->toBe(12_00);
    expect($method->invoke($widget, 12_00, 'year', 0))->toBe(1_00);
});

it('previous churn uses the same active base as current churn', function (): void {
    User::query()->create(['name' => 'A', 'email' => 'repair-r1-9@example.com']);
    $owner = User::query()->where('email', 'repair-r1-9@example.com')->firstOrFail();

    regression_createSubscription($owner, [
        'chip_status' => SubscriptionStatus::Canceled,
        'created_at' => now()->subMonths(2),
        'ends_at' => now()->subMonth()->startOfMonth()->addDays(10),
        'canceled_at' => now()->subMonth()->startOfMonth()->addDays(10),
    ]);

    $widget = app(ChurnRateWidget::class);
    $method = new ReflectionMethod(ChurnRateWidget::class, 'calculatePreviousChurnRate');

    expect($method->invoke($widget))->toBe(0.0);
});

it('mrr discounts are interval-aware in current and previous periods', function (): void {
    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-10@example.com']);

    regression_createSubscription($owner, [
        'billing_interval' => 'year',
        'billing_interval_count' => 1,
        'coupon_id' => 'coupon_yearly',
        'coupon_discount' => 12_000,
        'created_at' => now()->subMonths(2),
    ], ['unit_amount' => 120_000]);

    $widget = app(MRRWidget::class);
    $current = new ReflectionMethod(MRRWidget::class, 'calculateMRR');
    $previous = new ReflectionMethod(MRRWidget::class, 'calculatePreviousMRR');

    expect($current->invoke($widget))->toBe(9_000);
    expect($previous->invoke($widget))->toBe(9_000);
});

it('portal failures show generic messages without leaking internals', function (): void {
    $secret = 'SECRET-CHIP-500-INTERNALS';

    $failingSubscription = new class($secret)
    {
        public function __construct(private readonly string $secret) {}

        public function cancel(): void
        {
            throw new Exception($this->secret);
        }

        public function resume(): void
        {
            throw new Exception($this->secret);
        }
    };

    $billable = new class($failingSubscription) extends Model
    {
        public function __construct(private readonly ?object $subscription = null)
        {
            parent::__construct();
        }

        public function subscriptions(): object
        {
            return new class($this->subscription)
            {
                public function __construct(private readonly object $subscription) {}

                public function find(string $id): ?object
                {
                    return $this->subscription;
                }
            };
        }
    };

    $page = new class($billable) extends Subscriptions
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $page->cancelSubscription('sub_1');
    $page->resumeSubscription('sub_1');

    $notifications = collect(session()->get('filament.notifications', []));

    expect($notifications->pluck('title')->all())
        ->toContain('Failed to cancel subscription', 'Failed to resume subscription');

    foreach ($notifications as $notification) {
        expect((string) ($notification['body'] ?? ''))->not->toContain('SECRET-CHIP');
    }
});

it('payment method failures show generic messages without leaking internals', function (): void {
    $billable = new class extends Model
    {
        public function updateDefaultPaymentMethod(string $id): void
        {
            throw new Exception('SECRET-PM-STORE-FAILURE');
        }

        public function deletePaymentMethod(string $id): void
        {
            throw new Exception('SECRET-PM-DELETE-FAILURE');
        }
    };

    $page = new class($billable) extends PaymentMethods
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $page->setAsDefault('pm_1');
    $page->deletePaymentMethod('pm_1');

    $notifications = collect(session()->get('filament.notifications', []));

    expect($notifications->pluck('title')->all())
        ->toContain('Failed to update default payment method', 'Failed to delete payment method');

    foreach ($notifications as $notification) {
        expect((string) ($notification['body'] ?? ''))->not->toContain('SECRET-PM');
    }
});

it('subscriptions relation manager is only registered for subscriptions models', function (): void {
    $chipOnlyModel = new class extends Model
    {
        public function chipSubscriptions(): object
        {
            return new stdClass;
        }
    };

    Cashier::useCustomerModel($chipOnlyModel::class);

    try {
        expect(CustomerResource::getRelations())->not->toContain(SubscriptionsRelationManager::class);
    } finally {
        Cashier::useCustomerModel(User::class);
    }

    expect(CustomerResource::getRelations())->toContain(SubscriptionsRelationManager::class);
});

it('subscription infolist tolerates a missing customer', function (): void {
    $subscription = new Subscription;

    $schema = SubscriptionInfolist::configure(Schema::make(regression_makeSchemaLivewire()));
    $entry = $schema->getComponent('customer_chip_customer_id');

    expect($entry)->not->toBeNull();

    // Explicit try/catch: not->toThrow(Throwable::class) is vacuous on interfaces.
    $thrown = null;

    try {
        $entry->model($subscription)->getState();
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeNull();
    expect($entry->model($subscription)->getState())->toBeNull();
});

it('setup purchase is created lazily on click with failure feedback', function (): void {
    $billable = new class extends Model
    {
        public int $setupCalls = 0;

        /**
         * @param  array<string, mixed>  $options
         */
        public function setupPaymentMethodUrl(array $options = []): string
        {
            $this->setupCalls++;

            return 'https://gate.chip-in.asia/checkout/setup-9';
        }
    };

    $page = new class($billable) extends PaymentMethods
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }

        /**
         * @param  array<string, mixed>  $parameters
         */
        protected function billingRoute(string $name, array $parameters = []): string
        {
            return 'https://example.test/billing/payment-methods';
        }
    };

    $response = $page->redirectToAddPaymentMethod();

    expect($billable->setupCalls)->toBe(1);
    expect($response?->getTargetUrl())->toBe('https://gate.chip-in.asia/checkout/setup-9');

    $failingBillable = new class extends Model
    {
        /**
         * @param  array<string, mixed>  $options
         */
        public function setupPaymentMethodUrl(array $options = []): string
        {
            throw new Exception('SECRET-SETUP-FAILURE');
        }
    };

    $failingPage = new class($failingBillable) extends PaymentMethods
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }

        /**
         * @param  array<string, mixed>  $parameters
         */
        protected function billingRoute(string $name, array $parameters = []): string
        {
            return 'https://example.test/billing/payment-methods';
        }
    };

    expect($failingPage->redirectToAddPaymentMethod())->toBeNull();
    expect(regression_notificationTitles())->toContain('Unable to start adding a payment method');

    $blade = file_get_contents((string) realpath(__DIR__ . '/../../../../packages/filament-cashier-chip/resources/views/pages/payment-methods.blade.php'));

    expect($blade)->not->toContain('getAddPaymentMethodUrl')
        ->and($blade)->toContain('redirectToAddPaymentMethod');
});

it('portal invoice history passes an explicit configured cap', function (): void {
    $billable = new class extends Model
    {
        public ?int $receivedLimit = null;

        public function invoices(?int $limit = 25): Collection
        {
            $this->receivedLimit = $limit;

            return collect();
        }
    };

    $page = new class($billable) extends Invoices
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $method = new ReflectionMethod(Invoices::class, 'getInvoices');
    $method->invoke($page);

    expect($billable->receivedLimit)->toBe(25);

    config()->set('filament-cashier-chip.billing.invoices.limit', 7);
    $method->invoke($page);

    expect($billable->receivedLimit)->toBe(7);
});

it('navigation badge hides when no owner context can be resolved', function (): void {
    regression_enableOwnerScoping();
    regression_bindOwner(null);

    expect(SubscriptionResource::getNavigationBadge())->toBeNull();
});

it('navigation badge counts records through the resource query', function (): void {
    $owner = User::query()->create(['name' => 'A', 'email' => 'repair-r1-16@example.com']);
    regression_createSubscription($owner);

    expect(SubscriptionResource::getNavigationBadge())->toBe('1');
});

it('chart currency is allowlisted before JS interpolation', function (): void {
    config()->set('cashier-chip.currency', "EUR');alert(1);//");

    $widget = app(RevenueChartWidget::class);
    $method = new ReflectionMethod(RevenueChartWidget::class, 'getOptions');
    $options = $method->invoke($widget);

    $callback = $options['scales']['y']['ticks']['callback'];

    expect($callback)->toContain("'MYR '");
    expect($callback)->not->toContain('alert');
});

it('list invoices header exposes the reports action', function (): void {
    $page = app(ListInvoices::class);
    $method = new ReflectionMethod(ListInvoices::class, 'getHeaderActions');
    $headerNames = array_map(fn (Action $action): ?string => $action->getName(), $method->invoke($page));

    expect($headerNames)->toContain('view_reports');
});

it('relation manager status color tolerates null', function (): void {
    $method = new ReflectionMethod(SubscriptionsRelationManager::class, 'getStatusColor');

    expect($method->invoke(null, null))->toBe('gray');
    expect($method->invoke(null, SubscriptionStatus::Active))->toBe('success');
});

it('trial query cache can be reset for octane and tests', function (): void {
    $record = new User;
    $support = new ReflectionMethod(CustomerTable::class, 'supportsGenericTrialQuery');
    $support->invoke(null, $record);

    $property = new ReflectionProperty(CustomerTable::class, 'genericTrialQuerySupport');

    expect($property->getValue())->not->toBeEmpty();

    CustomerTable::resetGenericTrialQuerySupport();

    expect($property->getValue())->toBeEmpty();
});

it('subscription item forms bound quantity, price, and unit amount', function (): void {
    $manager = app(SubscriptionItemsRelationManager::class);
    $table = $manager->table(regression_makeTable());

    $actions = [];
    foreach ($table->getRecordActions() as $action) {
        $actions[$action->getName()] = $action;
    }

    expect($actions)->toHaveKeys(['update_quantity', 'swap_price']);

    $schemaProperty = new ReflectionProperty(Action::class, 'schema');

    $quantityComponents = $schemaProperty->getValue($actions['update_quantity']);
    $quantityInput = collect($quantityComponents)->firstWhere(fn ($component): bool => $component->getName() === 'quantity');

    expect((int) $quantityInput?->getMaxValue())->toBe(1_000_000);

    $swapComponents = $schemaProperty->getValue($actions['swap_price']);
    $byName = [];
    foreach ($swapComponents as $component) {
        $byName[$component->getName()] = $component;
    }

    expect($byName['price']?->getMaxLength())->toBe(255);
    expect((int) $byName['unit_amount']?->getMaxValue())->toBe(99_999_999_999);
});

it('missing invoice notifies instead of aborting', function (): void {
    $page = new class extends Invoices
    {
        protected function getBillable(): ?Model
        {
            return null;
        }
    };

    expect($page->downloadInvoice('inv_missing'))->toBeNull();
    expect(regression_notificationTitles())->toContain('Invoice not found');
});

it('portal lifecycle actions stay scoped to the billable', function (): void {
    $userA = User::query()->create(['name' => 'A', 'email' => 'repair-aud-b1-a@example.com']);
    $userB = User::query()->create(['name' => 'B', 'email' => 'repair-aud-b1-b@example.com']);

    $subscriptionB = regression_createSubscription($userB);

    $page = new class($userA) extends Subscriptions
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $page->cancelSubscription($subscriptionB->id);

    expect(regression_notificationTitles())->toContain('Subscription not found');
    expect($subscriptionB->refresh()->chip_status)->toBe(SubscriptionStatus::Active);
});
