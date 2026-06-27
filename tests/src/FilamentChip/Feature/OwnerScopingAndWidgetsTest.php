<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

function forcePurchase(array $attributes): Purchase
{
    return tap(new Purchase, fn (Purchase $p) => $p->forceFill($attributes)->save());
}
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    Schema::dropIfExists('tenants');
    Schema::dropIfExists('chip_purchases');

    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->integer('created_on')->nullable();
        $table->string('payment_method')->nullable();
        $table->json('purchase')->nullable();
        $table->json('payment')->nullable();
        $table->json('transaction_data')->nullable();
        $table->json('metadata')->nullable();
    });

    config()->set('chip.owner.enabled', true);
});

afterEach(function (): void {
    config()->set('chip.owner.enabled', true);
});

it('scopes filament resource queries to the current owner', function (): void {
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerB = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $ownerB->name = 'B';
    $ownerB->save();

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Purchase::withoutEvents(function () use ($ownerA, $ownerB): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'purchase' => ['amount' => 1000],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'purchase' => ['amount' => 2000],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => null,
            'owner_id' => null,
            'purchase' => ['amount' => 3000],
        ]);
    });

    expect(PurchaseResource::getEloquentQuery()->count())->toBe(1);
});

it('computes payment method breakdown without including test mode purchases', function (): void {
    Purchase::withoutEvents(function (): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'purchase' => ['amount' => 1000],
            'payment' => ['payment_type' => 'fpx'],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => true,
            'purchase' => ['amount' => 9999],
            'payment' => ['payment_type' => 'card'],
        ]);
    });

    /** @var array<string, array{count: int, amount: int}> $breakdown */
    $breakdown = OwnerContext::withOwner(null, function (): array {
        $widget = app(PaymentMethodsWidget::class);

        $ref = new ReflectionClass($widget);
        $method = $ref->getMethod('getPaymentMethodBreakdown');

        /** @var array<string, array{count: int, amount: int}> $result */
        $result = $method->invoke($widget);

        return $result;
    });

    expect($breakdown)->toHaveKey('FPX');
    expect($breakdown['FPX']['count'])->toBe(1);
    expect($breakdown['FPX']['amount'])->toBe(1000);
});

it('generates revenue data for the last 30 days', function (): void {
    $today = now();

    Purchase::withoutEvents(function () use ($today): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->startOfDay()->getTimestamp(),
            'purchase' => ['amount' => 1000],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->endOfDay()->getTimestamp(),
            'purchase' => ['amount' => 2000],
        ]);
    });

    /** @var array{labels: array<string>, amounts: array<int>} $data */
    $data = OwnerContext::withOwner(null, function (): array {
        $widget = app(RevenueChartWidget::class);

        $ref = new ReflectionClass($widget);
        $method = $ref->getMethod('getRevenueData');

        /** @var array{labels: array<string>, amounts: array<int>} $result */
        $result = $method->invoke($widget);

        return $result;
    });

    expect($data['labels'])->toHaveCount(30);
    expect($data['amounts'])->toHaveCount(30);
    expect(max($data['amounts']))->toBeGreaterThanOrEqual(30);
});

// Cross-tenant regression tests for Filament resource
it('resource rejects cross-tenant reads', function (): void {
    Schema::dropIfExists('tenants');
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerB = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $ownerB->name = 'B';
    $ownerB->save();

    // Create purchases for both owners
    Purchase::withoutEvents(function () use ($ownerA, $ownerB): void {
        forcePurchase([
            'id' => '11111111-1111-1111-1111-111111111111',
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'purchase' => ['amount' => 1000],
        ]);

        forcePurchase([
            'id' => '22222222-2222-2222-2222-222222222222',
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'purchase' => ['amount' => 2000],
        ]);
    });

    // Set context to OwnerA
    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    // OwnerA's resource should show 1 record
    expect(PurchaseResource::getEloquentQuery()->count())->toBe(1);

    // Switch context to OwnerB
    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    // OwnerB's resource should show 1 record (only their own)
    expect(PurchaseResource::getEloquentQuery()->count())->toBe(1);
    expect(PurchaseResource::getEloquentQuery()->first()->id)->toBe('22222222-2222-2222-2222-222222222222');
});

it('widget metrics reject cross-tenant data', function (): void {
    Schema::dropIfExists('tenants');
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerB = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $ownerB->name = 'B';
    $ownerB->save();

    $today = now();

    // Create purchases for both owners
    Purchase::withoutEvents(function () use ($ownerA, $ownerB, $today): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'created_on' => $today->getTimestamp(),
            'purchase' => ['amount' => 5000],
            'payment' => ['payment_type' => 'fpx'],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'created_on' => $today->getTimestamp(),
            'purchase' => ['amount' => 10000],
            'payment' => ['payment_type' => 'card'],
        ]);
    });

    // Set context to OwnerA
    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $widgetA = app(PaymentMethodsWidget::class);
    $refA = new ReflectionClass($widgetA);
    $methodA = $refA->getMethod('getPaymentMethodBreakdown');

    /** @var array<string, array{count: int, amount: int}> $breakdownA */
    $breakdownA = $methodA->invoke($widgetA);

    // OwnerA should only see FPX (5000)
    expect($breakdownA)->toHaveKey('FPX');
    expect($breakdownA['FPX']['amount'])->toBe(5000);

    // Switch context to OwnerB
    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    // Recreate widget with new context
    app()->forgetInstance(PaymentMethodsWidget::class);
    $widgetB = app(PaymentMethodsWidget::class);
    $refB = new ReflectionClass($widgetB);
    $methodB = $refB->getMethod('getPaymentMethodBreakdown');

    /** @var array<string, array{count: int, amount: int}> $breakdownB */
    $breakdownB = $methodB->invoke($widgetB);

    // OwnerB should only see Card (10000)
    expect($breakdownB)->toHaveKey('Card');
    expect($breakdownB['Card']['amount'])->toBe(10000);
});

it('renders chip stats using explicit global context when no owner is resolved', function (): void {
    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['amount' => 1000],
            'payment' => ['payment_type' => 'fpx'],
        ]);
    });

    $widget = app(ChipStatsWidget::class);
    $method = new ReflectionMethod(ChipStatsWidget::class, 'getStats');

    /** @var array<int, mixed> $stats */
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(4);
});

it('renders revenue chart using explicit global context when no owner is resolved', function (): void {
    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['amount' => 2000],
        ]);
    });

    $widget = app(RevenueChartWidget::class);
    $method = new ReflectionMethod(RevenueChartWidget::class, 'getData');

    /** @var array{datasets: array<int, array{data: array<int>}>, labels: array<string>} $data */
    $data = $method->invoke($widget);

    expect($data['labels'])->toHaveCount(30)
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(30)
        ->and(max($data['datasets'][0]['data']))->toBeGreaterThanOrEqual(20);
});

it('builds recent transactions query using explicit global context when no owner is resolved', function (): void {
    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        forcePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['amount' => 1000],
        ]);

        forcePurchase([
            'status' => 'paid',
            'is_test' => true,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['amount' => 9000],
        ]);
    });

    $widget = app(RecentTransactionsWidget::class);
    $method = new ReflectionMethod(RecentTransactionsWidget::class, 'getRecentTransactionsQuery');

    /** @var Builder<Purchase> $query */
    $query = $method->invoke($widget);

    expect($query->count())->toBe(1)
        ->and($query->first()?->is_test)->toBeFalse();
});
