<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Actions\PurchaseExporter;
use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ViewCompanyStatement;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use Carbon\CarbonImmutable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    config()->set('chip.owner.enabled', true);
});

it('clamps tampered analytics periods to the default range', function (): void {
    $recording = new class extends LocalAnalyticsService
    {
        public ?CarbonImmutable $seenStart = null;

        public ?CarbonImmutable $seenEnd = null;

        public function getDashboardMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): DashboardMetrics
        {
            $this->seenStart = $startDate;
            $this->seenEnd = $endDate;

            return new DashboardMetrics(
                revenue: new RevenueMetrics(
                    grossRevenue: 0,
                    refunds: 0,
                    netRevenue: 0,
                    transactionCount: 0,
                    averageTransaction: 0.0,
                    growthRate: 0.0,
                ),
                transactions: new TransactionMetrics(
                    total: 0,
                    successful: 0,
                    failed: 0,
                    pending: 0,
                    refunded: 0,
                    successRate: 0.0,
                ),
                paymentMethods: [],
                failures: [],
            );
        }

        public function getRevenueTrend(CarbonImmutable $startDate, CarbonImmutable $endDate, string $groupBy = 'day'): array
        {
            return [];
        }
    };

    app()->instance(LocalAnalyticsService::class, $recording);

    $page = new AnalyticsDashboardPage;
    $page->period = '999999';
    $page->loadMetrics();

    expect($page->period)->toBe('30');
    expect($recording->seenStart)->not->toBeNull();
    expect($recording->seenEnd)->not->toBeNull();
    expect((int) $recording->seenStart->diffInDays($recording->seenEnd))->toBe(30);

    $page->period = '7';
    $page->loadMetrics();

    expect($page->period)->toBe('7');
    expect((int) $recording->seenStart->diffInDays($recording->seenEnd))->toBe(7);

    $page->period = '-5';
    $page->loadMetrics();

    expect($page->period)->toBe('30');
});

it('omits signed checkout urls from purchase exports and formats test flags loosely', function (): void {
    $columns = PurchaseExporter::getColumns();
    $names = array_map(fn ($column): string => $column->getName(), $columns);

    expect($names)->not->toContain('checkout_url');

    $testColumn = collect($columns)->firstWhere(fn ($column): bool => $column->getName() === 'is_test');

    expect($testColumn)->not->toBeNull();
    expect($testColumn->formatState(1))->toBe('Yes');
    expect($testColumn->formatState(0))->toBe('No');
    expect($testColumn->formatState(null))->toBe('No');
    expect($testColumn->formatState(true))->toBe('Yes');
});

it('scopes export queries to the current owner', function (): void {
    Schema::dropIfExists('tenants');
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    Schema::dropIfExists('chip_purchases');
    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
    });

    $owner = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA = (clone $owner)->forceFill([
        'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'name' => 'A',
    ]);
    $ownerA->save();

    $ownerB = (clone $owner)->forceFill([
        'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'name' => 'B',
    ]);
    $ownerB->save();

    Purchase::withoutEvents(function () use ($ownerA, $ownerB): void {
        tap(new Purchase, fn (Purchase $p) => $p->forceFill([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ])->save());

        tap(new Purchase, fn (Purchase $p) => $p->forceFill([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ])->save());
    });

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(PurchaseExporter::modifyQuery(Purchase::query())->count())->toBe(1);
});

it('rejects cross-owner statement resolution', function (): void {
    Schema::dropIfExists('tenants');
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    Schema::dropIfExists('chip_company_statements');
    Schema::create('chip_company_statements', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    $ownerA = (new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    })->forceFill(['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'name' => 'A']);
    $ownerA->save();

    $ownerB = (new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    })->forceFill(['id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'name' => 'B']);
    $ownerB->save();

    $statementA = OwnerContext::withOwner($ownerA, fn (): CompanyStatement => CompanyStatement::query()->create([
        'status' => 'completed',
    ]));
    $statementB = OwnerContext::withOwner($ownerB, fn (): CompanyStatement => CompanyStatement::query()->create([
        'status' => 'completed',
    ]));

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $page = new ViewCompanyStatement;
    $method = new ReflectionMethod(ViewCompanyStatement::class, 'resolveScopedCompanyStatement');

    expect($method->invoke($page, $statementA)?->getKey())->toBe($statementA->getKey());
    expect($method->invoke($page, $statementB))->toBeNull();
});

it('caches distinct filter options per owner', function (): void {
    Schema::dropIfExists('chip_clients');
    Schema::create('chip_clients', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('country')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('chip_payments');
    Schema::create('chip_payments', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('currency')->nullable();
        $table->timestamps();
    });

    Client::withoutEvents(function (): void {
        tap(new Client, fn (Client $c) => $c->forceFill(['country' => 'my'])->save());
        tap(new Client, fn (Client $c) => $c->forceFill(['country' => 'sg'])->save());
    });

    Payment::withoutEvents(function (): void {
        tap(new Payment, fn (Payment $p) => $p->forceFill(['currency' => 'MYR'])->save());
        tap(new Payment, fn (Payment $p) => $p->forceFill(['currency' => 'USD'])->save());
    });

    $filterQueries = OwnerContext::withOwner(null, function (): array {
        $clientTable = ClientResource::table(Table::make(new ListClients));
        $paymentTable = PaymentResource::table(Table::make(new ListPayments));

        $countryFilter = $clientTable->getFilters()['country'];
        $currencyFilter = $paymentTable->getFilters()['currency'];

        DB::enableQueryLog();
        $countriesFirst = $countryFilter->getOptions();
        $countriesSecond = $countryFilter->getOptions();
        $currenciesFirst = $currencyFilter->getOptions();
        $currenciesSecond = $currencyFilter->getOptions();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($countriesFirst)->toBe(['my' => 'MY', 'sg' => 'SG']);
        expect($countriesSecond)->toBe($countriesFirst);
        expect($currenciesFirst)->toBe(['MYR' => 'MYR', 'USD' => 'USD']);
        expect($currenciesSecond)->toBe($currenciesFirst);

        return $queries;
    });

    $distinctQueries = array_values(array_filter(
        $filterQueries,
        fn (array $query): bool => str_contains(mb_strtolower($query['query']), 'distinct'),
    ));

    expect($distinctQueries)->toHaveCount(2);
});
