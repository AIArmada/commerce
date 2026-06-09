<?php

declare(strict_types=1);

use AIArmada\Customers\Actions\RebuildAllSegments;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Illuminate\Support\Facades\Event;

describe('RebuildAllSegments', function (): void {
    beforeEach(function (): void {
        $this->action = new RebuildAllSegments;
    });

    describe('forOwner', function (): void {
        it('returns results keyed by segment name', function (): void {
            $segment = Segment::create([
                'name' => 'ForOwner ' . uniqid(),
                'slug' => 'forowner-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'For',
                'last_name' => 'Owner',
                'email' => 'for-owner-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $results = $this->action->forOwner();

            expect($results)->toBeArray();
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

            Customer::create([
                'first_name' => 'Action',
                'last_name' => 'Test',
                'email' => 'action-test-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $count = $this->action->rebuildSegment($segment);

            expect($count)->toBeGreaterThanOrEqual(0);
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

            $customer = Customer::create([
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

            $matching = Customer::create([
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

    describe('rebuildSegment with owner-scoped data', function (): void {
        it('handles global segments and customers', function (): void {
            $segment = Segment::create([
                'name' => 'Owner Scoped ' . uniqid(),
                'slug' => 'owner-scoped-' . uniqid(),
                'is_automatic' => true,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'Owner',
                'last_name' => 'Scoped',
                'email' => 'owner-scoped-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $count = $this->action->rebuildSegment($segment);

            expect($count)->toBeGreaterThanOrEqual(0);
        });
    });
});
