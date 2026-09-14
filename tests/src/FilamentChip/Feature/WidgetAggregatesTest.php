<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Resources\BaseChipResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentChip\Widgets\TokenStatsWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! function_exists('chipAggregatePurchase')) {
    function chipAggregatePurchase(array $attributes): Purchase
    {
        return tap(new Purchase, fn (Purchase $p) => $p->forceFill($attributes)->save());
    }
}

beforeEach(function (): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    Schema::dropIfExists('chip_purchases');

    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->integer('created_on')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('recurring_token')->nullable();
        $table->json('purchase')->nullable();
        $table->json('payment')->nullable();
        $table->json('transaction_data')->nullable();
        $table->json('metadata')->nullable();
    });

    config()->set('chip.owner.enabled', true);
});

it('sums period revenue in sql without hydrating purchases', function (): void {
    Purchase::withoutEvents(function (): void {
        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['total' => 1000],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['total' => 2000],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => true,
            'created_on' => now()->getTimestamp(),
            'purchase' => ['total' => 9999],
        ]);
    });

    $total = OwnerContext::withOwner(null, function (): int {
        $widget = app(ChipStatsWidget::class);
        $method = new ReflectionMethod(ChipStatsWidget::class, 'getRevenueForPeriod');

        DB::enableQueryLog();
        $result = $method->invoke($widget, now()->startOfDay()->toImmutable());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
        expect($queries[0]['query'])->toContain('sum');

        return $result;
    });

    expect($total)->toBe(3000);
});

it('groups payment methods in sql and merges equivalent labels', function (): void {
    Purchase::withoutEvents(function (): void {
        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'purchase' => ['total' => 1000],
            'payment' => ['payment_type' => 'fpx'],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'purchase' => ['total' => 2000],
            'payment' => ['payment_type' => 'credit_card'],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'purchase' => ['total' => 3000],
            'transaction_data' => ['payment_method' => 'card'],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => true,
            'purchase' => ['total' => 9999],
            'payment' => ['payment_type' => 'fpx'],
        ]);
    });

    /** @var array<string, array{count: int, amount: int}> $breakdown */
    $breakdown = OwnerContext::withOwner(null, function (): array {
        $widget = app(PaymentMethodsWidget::class);
        $method = new ReflectionMethod(PaymentMethodsWidget::class, 'getPaymentMethodBreakdown');

        DB::enableQueryLog();
        $result = $method->invoke($widget);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
        expect(mb_strtolower($queries[0]['query']))->toContain('group by');

        return $result;
    });

    expect($breakdown['FPX']['count'])->toBe(1);
    expect($breakdown['FPX']['amount'])->toBe(1000);
    expect($breakdown['Card']['count'])->toBe(2);
    expect($breakdown['Card']['amount'])->toBe(5000);
});

it('buckets chart revenue by day in a single query', function (): void {
    $today = now();

    Purchase::withoutEvents(function () use ($today): void {
        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->startOfDay()->getTimestamp(),
            'purchase' => ['total' => 1000],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->subDays(5)->startOfDay()->addHour()->getTimestamp(),
            'purchase' => ['total' => 2500],
        ]);
    });

    /** @var array{labels: array<string>, amounts: array<int>} $data */
    $data = OwnerContext::withOwner(null, function (): array {
        $widget = app(RevenueChartWidget::class);
        $method = new ReflectionMethod(RevenueChartWidget::class, 'getRevenueData');

        DB::enableQueryLog();
        $result = $method->invoke($widget);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
        expect(mb_strtolower($queries[0]['query']))->toContain('group by');

        return $result;
    });

    expect($data['labels'])->toHaveCount(30);
    expect($data['amounts'])->toHaveCount(30);
    expect($data['amounts'][29])->toBe(10);
    expect($data['amounts'][24])->toBe(25);
    expect(array_sum($data['amounts']))->toBe(35);
});

it('sums token revenue in sql', function (): void {
    Purchase::withoutEvents(function (): void {
        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'recurring_token' => 'tok_1',
            'purchase' => ['total' => 4000],
        ]);

        chipAggregatePurchase([
            'status' => 'settled',
            'is_test' => false,
            'recurring_token' => 'tok_1',
            'purchase' => ['total' => 1000],
        ]);

        chipAggregatePurchase([
            'status' => 'paid',
            'is_test' => false,
            'recurring_token' => null,
            'purchase' => ['total' => 9999],
        ]);
    });

    /** @var array{active_tokens: int, token_purchases: int, token_revenue: int} $stats */
    $stats = OwnerContext::withOwner(null, function (): array {
        $widget = app(TokenStatsWidget::class);
        $method = new ReflectionMethod(TokenStatsWidget::class, 'getTokenStats');

        DB::enableQueryLog();
        $result = $method->invoke($widget);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(3);

        foreach ($queries as $query) {
            $sql = mb_strtolower($query['query']);
            expect(str_contains($sql, 'count') || str_contains($sql, 'sum'))->toBeTrue();
        }

        return $result;
    });

    expect($stats['token_purchases'])->toBe(2);
    expect($stats['token_revenue'])->toBe(5000);
});

it('caches navigation badges per owner', function (): void {
    Purchase::withoutEvents(function (): void {
        chipAggregatePurchase(['status' => 'paid', 'is_test' => false]);
        chipAggregatePurchase(['status' => 'paid', 'is_test' => false]);
    });

    $badges = OwnerContext::withOwner(null, function (): array {
        DB::enableQueryLog();
        $first = PurchaseResource::getNavigationBadge();
        $second = PurchaseResource::getNavigationBadge();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $countQueries = array_values(array_filter(
            $queries,
            fn (array $query): bool => str_contains(mb_strtolower($query['query']), 'count'),
        ));

        expect($countQueries)->toHaveCount(1);

        return [$first, $second];
    });

    expect($badges)->toBe(['2', '2']);
});

it('returns an empty query for models without owner scope', function (): void {
    Schema::dropIfExists('chip_noscope_records');

    Schema::create('chip_noscope_records', function (Blueprint $table): void {
        $table->increments('id');
    });

    DB::table('chip_noscope_records')->insert([['id' => 1]]);

    $modelClass = new class extends Model
    {
        protected $table = 'chip_noscope_records';

        protected $guarded = [];
    };

    $resource = new class($modelClass::class) extends BaseChipResource
    {
        public function __construct(private string $modelFqcn) {}

        protected static ?string $model;

        protected static function navigationSortKey(): string
        {
            return 'purchases';
        }

        public static function getModel(): string
        {
            return static::$model;
        }

        public static function setModel(string $model): void
        {
            static::$model = $model;
        }
    };

    $resource::setModel($modelClass::class);

    $query = $resource::getEloquentQuery();

    expect($query)->toBeInstanceOf(Builder::class);
    expect($query->toSql())->toContain('1 = 0');
    expect($query->count())->toBe(0);
});
