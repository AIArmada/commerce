<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Actions\RebuildAllSegments;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

/**
 * @param  array<string, mixed>  $attributes
 */
function createRebuildActionTestCustomer(array $attributes): Customer
{
    $restricted = [];

    foreach (['user_id', 'status', 'is_guest', 'accepts_marketing', 'created_at', 'updated_at'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $restricted[$key] = $attributes[$key];
            unset($attributes[$key]);
        }
    }

    $customer = Customer::query()->create($attributes);

    if ($restricted !== []) {
        $customer->forceFill($restricted)->save();
    }

    return $customer;
}

describe('RebuildAllSegments', function (): void {
    beforeEach(function (): void {
        $this->action = new RebuildAllSegments;
    });

    describe('forOwner', function (): void {
        beforeEach(function (): void {
            Schema::dropIfExists('test_owners');

            Schema::create('test_owners', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        });

        it('returns results keyed by segment name', function (): void {
            /** @var Model $owner */
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);

            $segment = OwnerContext::withOwner($owner, fn (): Segment => Segment::create([
                'name' => 'ForOwner ' . uniqid(),
                'slug' => 'forowner-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]));

            OwnerContext::withOwner($owner, function (): void {
                createRebuildActionTestCustomer([
                    'first_name' => 'For',
                    'last_name' => 'Owner',
                    'email' => 'for-owner-' . uniqid() . '@example.com',
                    'status' => CustomerStatus::Active,
                    'accepts_marketing' => true,
                ]);
            });

            $results = $this->action->forOwner($owner);

            expect($results)->toBe([$segment->name => 1]);
        });
    });

    describe('rebuildSegment', function (): void {
        it('returns customer count for automatic segment', function (): void {
            $segment = Segment::create([
                'name' => 'Rebuild Action ' . uniqid(),
                'slug' => 'rebuild-action-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            createRebuildActionTestCustomer([
                'first_name' => 'Action',
                'last_name' => 'Test',
                'email' => 'action-test-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $count = $this->action->rebuildSegment($segment);

            expect($count)->toBe(1);
        });

        it('returns existing count for manual segment without changes', function (): void {
            $segment = Segment::create([
                'name' => 'Manual Count ' . uniqid(),
                'slug' => 'manual-count-' . uniqid(),
                'is_automatic' => false,
            ]);

            $count = $this->action->rebuildSegment($segment);

            expect($count)->toBe(0);
        });

        it('fires events for added customers', function (): void {
            Event::fake();

            $segment = Segment::create([
                'name' => 'Event Add ' . uniqid(),
                'slug' => 'event-add-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            $customer = createRebuildActionTestCustomer([
                'first_name' => 'Event',
                'last_name' => 'Add',
                'email' => 'event-add-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $this->action->rebuildSegment($segment);

            Event::assertDispatched(CustomerSegmentChanged::class, function (CustomerSegmentChanged $event) use ($customer, $segment): bool {
                return $event->customer->id === $customer->id
                    && $event->segment->id === $segment->id
                    && $event->action === 'added';
            });
        });

        it('fires events for removed customers', function (): void {
            Event::fake();

            $segment = Segment::create([
                'name' => 'Event Remove ' . uniqid(),
                'slug' => 'event-remove-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            $customer = Customer::create([
                'first_name' => 'Event',
                'last_name' => 'Remove',
                'email' => 'event-remove-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $segment->customers()->attach($customer->id);

            $this->action->rebuildSegment($segment);

            Event::assertDispatched(CustomerSegmentChanged::class, function (CustomerSegmentChanged $event) use ($customer, $segment): bool {
                return $event->customer->id === $customer->id
                    && $event->segment->id === $segment->id
                    && $event->action === 'removed';
            });
        });

        it('syncs matching customers to the segment', function (): void {
            $segment = Segment::create([
                'name' => 'Sync Test ' . uniqid(),
                'slug' => 'sync-test-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            $matching = createRebuildActionTestCustomer([
                'first_name' => 'Sync',
                'last_name' => 'Match',
                'email' => 'sync-match-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $nonMatching = Customer::create([
                'first_name' => 'Sync',
                'last_name' => 'NoMatch',
                'email' => 'sync-nomatch-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $segment->customers()->attach([$matching->id, $nonMatching->id]);

            $this->action->rebuildSegment($segment);

            $segmentCustomers = $segment->fresh()->customers;

            expect($segmentCustomers->pluck('id'))->toContain($matching->id);
            expect($segmentCustomers->pluck('id'))->not->toContain($nonMatching->id);
        });
    });

});
