<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('makeCustomerSegmentFilterOwner')) {
    function makeCustomerSegmentFilterOwner(string $id): Model
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
                return 'tests:customer-segment-owner';
            }
        };
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
    config()->set('customers.features.owner.auto_assign_on_create', true);
});

function bindCustomerSegmentFilterOwner(Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function createCustomerSegment(Model $owner, string $name, string $slug): Segment
{
    return OwnerContext::withOwner($owner, static fn (): Segment => Segment::query()->create([
        'name' => $name,
        'slug' => $slug,
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
    ]));
}

function customerSegmentFilter(): SelectFilter
{
    $livewire = Mockery::mock(HasTable::class);

    $filter = CustomerResource::table(Table::make($livewire))->getFilter('segments');

    if (! $filter instanceof SelectFilter) {
        throw new RuntimeException('The segments filter is not a select filter.');
    }

    return $filter;
}

it('searches segment filter options with an owner-scoped bounded query', function (): void {
    $ownerA = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000b');

    bindCustomerSegmentFilterOwner($ownerA);

    $segmentA = createCustomerSegment($ownerA, 'Alpha VIP', 'alpha-vip-a');
    $segmentB = createCustomerSegment($ownerB, 'Alpha Secret', 'alpha-secret-b');

    foreach (range(1, 55) as $index) {
        createCustomerSegment($ownerA, sprintf('Bulk %02d', $index), sprintf('bulk-%02d', $index));
    }

    $filter = customerSegmentFilter();
    $search = $filter->getSearchResultsUsingCallback();

    if (! $search instanceof Closure) {
        throw new RuntimeException('The segments filter has no search callback.');
    }

    $results = OwnerContext::withOwner($ownerA, static fn (): array => $search('Alpha'));
    $bulkResults = OwnerContext::withOwner($ownerA, static fn (): array => $search('Bulk'));

    expect($results)
        ->toHaveKey($segmentA->id)
        ->not->toHaveKey($segmentB->id)
        ->and($bulkResults)->toHaveCount(50);
});

it('resolves labels for selected owner-scoped segments only', function (): void {
    $ownerA = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000b');

    bindCustomerSegmentFilterOwner($ownerA);

    $segmentA = createCustomerSegment($ownerA, 'Selected A', 'selected-a');
    $segmentB = createCustomerSegment($ownerB, 'Selected B', 'selected-b');

    $filter = customerSegmentFilter();
    $labels = $filter->getOptionLabelsUsingCallback();

    if (! $labels instanceof Closure) {
        throw new RuntimeException('The segments filter has no option label callback.');
    }

    $resolved = OwnerContext::withOwner($ownerA, static fn (): array => $labels([$segmentA->id, $segmentB->id]));

    expect($resolved)->toEqual([$segmentA->id => 'Selected A']);
});

it('keeps multi-select filter semantics and rejects foreign segment ids', function (): void {
    $ownerA = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = makeCustomerSegmentFilterOwner('00000000-0000-0000-0000-00000000000b');

    bindCustomerSegmentFilterOwner($ownerA);

    $segmentA = createCustomerSegment($ownerA, 'Owner A Segment', 'owner-a-segment');
    $segmentB = createCustomerSegment($ownerB, 'Owner B Segment', 'owner-b-segment');

    $customerA = OwnerContext::withOwner($ownerA, static fn (): Customer => Customer::query()->create([
        'first_name' => 'Alice',
        'last_name' => 'OwnerA',
        'status' => 'active',
        'accepts_marketing' => false,
    ]));

    $customerB = OwnerContext::withOwner($ownerB, static fn (): Customer => Customer::query()->create([
        'first_name' => 'Bob',
        'last_name' => 'OwnerB',
        'status' => 'active',
        'accepts_marketing' => false,
    ]));

    $customerA->segments()->attach($segmentA);
    $customerB->segments()->attach($segmentB);

    $filter = customerSegmentFilter();

    $matchingIds = OwnerContext::withOwner($ownerA, static function () use ($filter, $segmentA): array {
        $query = Customer::query();
        $filter->apply($query, ['values' => [$segmentA->id]]);

        return $query->pluck('id')->all();
    });

    $foreignIds = OwnerContext::withOwner($ownerA, static function () use ($filter, $segmentB): array {
        $query = Customer::query();
        $filter->apply($query, ['values' => [$segmentB->id]]);

        return $query->pluck('id')->all();
    });

    expect($matchingIds)->toEqual([$customerA->id])
        ->and($foreignIds)->toBe([]);
});
