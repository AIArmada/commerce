<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\FilamentCustomersServiceProvider;
use AIArmada\FilamentCustomers\Pages\SegmentRebuildPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

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

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

if (! function_exists('filamentCustomers_makeUser')) {
    function filamentCustomers_makeUser(string $prefix): User
    {
        return User::query()->create([
            'name' => 'Segment Admin',
            'email' => $prefix . '-' . uniqid() . '@example.com',
            'password' => 'password',
        ]);
    }
}

if (! function_exists('filamentCustomers_makeSegment')) {
    function filamentCustomers_makeSegment(Model $owner, string $name, bool $automatic = true): Segment
    {
        return OwnerContext::withOwner($owner, fn (): Segment => Segment::query()->create([
            'name' => $name,
            'slug' => str($name)->slug() . '-' . uniqid(),
            'type' => 'custom',
            'is_automatic' => $automatic,
            'is_active' => true,
            'conditions' => [],
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]));
    }
}

it('queues the real rebuild command with segment and owner options', function (): void {
    Queue::fake();

    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $user = filamentCustomers_makeUser('rebuild');

    test()->actingAs($user);

    Gate::before(fn (mixed $authenticatedUser, string $ability): ?bool => $authenticatedUser?->is($user) === true
        && in_array($ability, ['customers.segments.rebuild', 'customers.segments.update'], true)
        ? true
        : null);

    $segment = filamentCustomers_makeSegment($owner, 'Rebuildable');

    OwnerContext::withOwner($owner, fn (): mixed => (new SegmentRebuildPage)->rebuildSegment($segment->id));

    Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) use ($segment, $owner): bool {
        $data = (fn (): mixed => $this->data)->call($job);

        return is_array($data)
            && ($data[0] ?? null) === 'customers:rebuild-segments'
            && ($data[1]['--segment'] ?? null) === $segment->getKey()
            && ($data[1]['--owner-type'] ?? null) === $owner->getMorphClass()
            && ($data[1]['--owner-id'] ?? null) === (string) $owner->getKey();
    });
});

it('denies single-segment rebuilds without the rebuild ability', function (): void {
    Queue::fake();

    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $segment = filamentCustomers_makeSegment($owner, 'Guarded');

    expect(fn (): mixed => OwnerContext::withOwner($owner, fn (): mixed => (new SegmentRebuildPage)->rebuildSegment($segment->id)))
        ->toThrow(AuthorizationException::class);

    Queue::assertNothingPushed();
});

it('rebuild-all authorizes every scoped segment then queues one scoped command', function (): void {
    Queue::fake();

    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $user = filamentCustomers_makeUser('rebuild-all');

    test()->actingAs($user);

    Gate::before(fn (mixed $authenticatedUser, string $ability): ?bool => $authenticatedUser?->is($user) === true
        && in_array($ability, ['customers.segments.rebuild', 'customers.segments.update'], true)
        ? true
        : null);

    filamentCustomers_makeSegment($owner, 'First Auto');
    filamentCustomers_makeSegment($owner, 'Second Auto');
    filamentCustomers_makeSegment($owner, 'Manual One', automatic: false);

    OwnerContext::withOwner($owner, fn (): mixed => (new SegmentRebuildPage)->rebuildAllSegments());

    $pushed = Queue::pushed(QueuedCommand::class);

    expect($pushed)->toHaveCount(1);

    $data = (fn (): mixed => $this->data)->call($pushed->first());

    expect($data[0])->toBe('customers:rebuild-segments')
        ->and($data[1] ?? [])->not->toHaveKey('--segment')
        ->and($data[1]['--owner-id'] ?? null)->toBe((string) $owner->getKey());
});

it('lists segments with a single counting query and a row cap', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    filamentCustomers_makeSegment($owner, 'Counted A');
    filamentCustomers_makeSegment($owner, 'Counted B');
    filamentCustomers_makeSegment($owner, 'Counted C');

    $queries = [];

    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $segments = OwnerContext::withOwner($owner, fn (): array => (new SegmentRebuildPage)->getSegments());

    expect($segments)->toHaveCount(3)
        ->and($queries)->toHaveCount(1)
        ->and($segments[0])->toHaveKeys(['id', 'name', 'type', 'customer_count']);

    foreach (range(1, 105) as $index) {
        filamentCustomers_makeSegment($owner, "Overflow {$index}");
    }

    $capped = OwnerContext::withOwner($owner, fn (): array => (new SegmentRebuildPage)->getSegments());

    expect($capped)->toHaveCount(100);
});

it('registers the segment-rebuild blade view', function (): void {
    $this->app->register(FilamentCustomersServiceProvider::class);

    expect(view()->exists('filament-customers::pages.segment-rebuild'))->toBeTrue();
});
