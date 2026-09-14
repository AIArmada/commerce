<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

if (! function_exists('filamentCustomers_makeOwner')) {
    function filamentCustomers_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:owner';
            }
        };
    }
}

it('sorts the customer name column by real name columns, not the accessor', function (): void {
    config()->set('customers.features.owner.enabled', false);

    Customer::query()->create([
        'first_name' => 'Zed',
        'last_name' => 'Alpha',
        'status' => 'active',
        'accepts_marketing' => false,
    ]);

    Customer::query()->create([
        'first_name' => 'Amy',
        'last_name' => 'Zulu',
        'status' => 'active',
        'accepts_marketing' => false,
    ]);

    $livewire = Mockery::mock(HasTable::class);
    $table = CustomerResource::table(Table::make($livewire));

    $column = $table->getColumn('full_name');

    expect($column->isSortable())->toBeTrue();

    $query = $column->applySort(Customer::query(), 'desc');

    expect($query->toSql())->toContain('last_name')
        ->and($query->toSql())->toContain('first_name')
        ->and($query->toSql())->not->toContain('full_name');

    $ordered = $query->pluck('last_name')->all();

    expect($ordered)->toEqual(['Zulu', 'Alpha']);
});

it('caches navigation badges per owner instead of counting on every render', function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);

    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Badge',
        'last_name' => 'Customer',
        'status' => 'active',
        'accepts_marketing' => false,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]));

    $countAggregateQueries = static fn (): int => collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains(mb_strtolower($entry['query']), 'count('))
        ->count();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $first = OwnerContext::withOwner($owner, fn (): ?string => CustomerResource::getNavigationBadge());
    $second = OwnerContext::withOwner($owner, fn (): ?string => CustomerResource::getNavigationBadge());

    expect($first)->toBe('1')
        ->and($second)->toBe('1')
        ->and($countAggregateQueries())->toBe(1);

    DB::flushQueryLog();

    OwnerContext::withOwner($owner, fn (): ?string => SegmentResource::getNavigationBadge());
    OwnerContext::withOwner($owner, fn (): ?string => SegmentResource::getNavigationBadge());

    expect($countAggregateQueries())->toBe(1);

    DB::disableQueryLog();
});
