<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SyncPrimaryEventOccurrenceAction;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Events\EventOccurrenceRescheduled;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);

    $this->event = Event::factory()->create(['timezone' => 'UTC']);
});

function syncPrimary(Event $event, array $attributes = []): ?EventOccurrence
{
    return app(SyncPrimaryEventOccurrenceAction::class)->handle($event, $attributes);
}

function makeOccurrence(Event $event, array $attributes = []): EventOccurrence
{
    return EventOccurrence::factory()->create(array_merge([
        'event_id' => $event->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
        'timezone' => 'UTC',
        'status' => 'scheduled',
    ], $attributes));
}

// Creation honors the omitted/default versus explicit-null end contract.
it('creates the primary occurrence with a default end when ends_at is omitted', function (): void {
    $occurrence = syncPrimary($this->event, [
        'title' => 'Launch Day',
        'starts_at' => '2026-07-01 09:00:00',
    ]);

    expect($occurrence)->toBeInstanceOf(EventOccurrence::class)
        ->and($occurrence->ends_at->toDateTimeString())->toBe('2026-07-01 11:00:00');
});

it('creates an open-ended primary occurrence when ends_at is explicitly null', function (): void {
    $occurrence = syncPrimary($this->event, [
        'title' => 'Open Day',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => null,
    ]);

    expect($occurrence->ends_at)->toBeNull();
});

// Published/postponed null-end schedules pass through the lifecycle workflow.
it('reschedules a published occurrence with an unknown end through the lifecycle', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'published',
        'starts_at' => CarbonImmutable::parse('2026-07-01 01:00:00'),
        'ends_at' => null,
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    EventFacade::fake([EventOccurrenceRescheduled::class]);

    $rescheduled = syncPrimary($this->event, [
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => null,
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $fresh = $rescheduled->fresh();

    expect($fresh->status->getValue())->toBe('rescheduled')
        ->and($fresh->ends_at)->toBeNull()
        ->and($fresh->starts_at->timestamp)->toBe(CarbonImmutable::parse('2026-07-02 09:00:00', 'Asia/Kuala_Lumpur')->timestamp)
        ->and($fresh->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($fresh->rescheduled_at)->not->toBeNull()
        ->and($fresh->changeLogs)->toHaveCount(1);

    EventFacade::assertDispatched(EventOccurrenceRescheduled::class);
});

it('keeps the stored end for a postponed occurrence when ends_at is omitted', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'postponed',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $rescheduled = syncPrimary($this->event, [
        'starts_at' => '2026-07-01 10:00:00',
    ]);

    $fresh = $rescheduled->fresh();

    expect($fresh->status->getValue())->toBe('rescheduled')
        ->and($fresh->starts_at->toDateTimeString())->toBe('2026-07-01 10:00:00')
        ->and($fresh->ends_at->toDateTimeString())->toBe('2026-07-01 11:00:00');
});

it('rejects a postponed resync when the moved start passes the stored end', function (): void {
    makeOccurrence($this->event, [
        'status' => 'postponed',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    syncPrimary($this->event, [
        'starts_at' => '2026-07-01 12:00:00',
    ]);
})->throws(InvalidArgumentException::class, 'end time must be after the start time');

// Identical re-syncs are no-ops and never re-transition published occurrences.
it('leaves a published occurrence untouched when the schedule is identical', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'published',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $result = syncPrimary($this->event, [
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'UTC',
    ]);

    $fresh = $result->fresh();

    expect($fresh->status->getValue())->toBe('published')
        ->and($fresh->rescheduled_at)->toBeNull()
        ->and($fresh->changeLogs)->toHaveCount(0);
});

it('treats the same instant in another timezone as a no-op for published occurrences', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'published',
        'starts_at' => CarbonImmutable::parse('2026-07-01 01:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 03:00:00'),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $result = syncPrimary($this->event, [
        'starts_at' => CarbonImmutable::parse('2026-07-01 01:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 03:00:00', 'UTC'),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    expect($result->fresh()->status->getValue())->toBe('published')
        ->and($result->fresh()->changeLogs)->toHaveCount(0);
});

it('updates non-schedule attributes on a no-op sync without transitioning', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'published',
        'title' => 'Old Title',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $result = syncPrimary($this->event, [
        'title' => 'New Title',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'UTC',
    ]);

    $fresh = $result->fresh();

    expect($fresh->title)->toBe('New Title')
        ->and($fresh->status->getValue())->toBe('published')
        ->and($fresh->changeLogs)->toHaveCount(0);
});

// Terminal occurrences refuse schedule changes but tolerate identical re-syncs.
it('refuses schedule changes on terminal occurrences', function (): void {
    makeOccurrence($this->event, [
        'status' => 'cancelled',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    syncPrimary($this->event, [
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => '2026-07-02 11:00:00',
    ]);
})->throws(InvalidArgumentException::class, 'Terminal occurrences cannot be rescheduled');

it('accepts an identical re-sync on a terminal occurrence', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'completed',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $result = syncPrimary($this->event, [
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'UTC',
    ]);

    expect($result->id)->toBe($occurrence->id)
        ->and($result->fresh()->status->getValue())->toBe('completed');
});

// Timezone handling: wall clocks parse in the resolved timezone and store UTC.
it('parses wall-clock strings in the given timezone and stores the UTC instant', function (): void {
    makeOccurrence($this->event, ['status' => 'scheduled']);

    $result = syncPrimary($this->event, [
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $fresh = $result->fresh();

    expect($fresh->starts_at->timestamp)->toBe(CarbonImmutable::parse('2026-07-01 09:00:00', 'Asia/Kuala_Lumpur')->timestamp)
        ->and($fresh->ends_at->timestamp)->toBe(CarbonImmutable::parse('2026-07-01 11:00:00', 'Asia/Kuala_Lumpur')->timestamp)
        ->and($fresh->timezone)->toBe('Asia/Kuala_Lumpur');
});

// Draft/scheduled occurrences update in place without lifecycle transitions.
it('keeps a scheduled occurrence scheduled when the end is omitted or cleared', function (): void {
    makeOccurrence($this->event, [
        'status' => 'scheduled',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $kept = syncPrimary($this->event, ['starts_at' => '2026-07-01 10:00:00']);

    expect($kept->fresh()->status->getValue())->toBe('scheduled')
        ->and($kept->fresh()->ends_at->toDateTimeString())->toBe('2026-07-01 11:00:00');

    $cleared = syncPrimary($this->event, [
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => null,
    ]);

    expect($cleared->fresh()->status->getValue())->toBe('scheduled')
        ->and($cleared->fresh()->ends_at)->toBeNull();
});

it('returns the primary occurrence when no start time is given', function (): void {
    $occurrence = makeOccurrence($this->event);

    expect(syncPrimary($this->event, [])->id)->toBe($occurrence->id)
        ->and(syncPrimary(Event::factory()->create(), []))->toBeNull();
});

// Changed lifecycle syncs still apply requested non-schedule attributes.
it('applies title and visibility when a published occurrence is rescheduled through sync', function (): void {
    makeOccurrence($this->event, [
        'status' => 'published',
        'title' => 'Old Title',
        'visibility' => 'public',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $result = syncPrimary($this->event, [
        'title' => 'New Title',
        'visibility' => 'hidden',
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => '2026-07-02 11:00:00',
        'timezone' => 'UTC',
    ]);

    $fresh = $result->fresh();

    expect($fresh->status->getValue())->toBe('rescheduled')
        ->and($fresh->title)->toBe('New Title')
        ->and($fresh->visibility)->toBe('hidden')
        ->and($fresh->starts_at->toDateTimeString())->toBe('2026-07-02 09:00:00')
        ->and($fresh->changeLogs)->toHaveCount(1);
});

it('saves and logs a repeated reschedule on an already-rescheduled occurrence', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'rescheduled',
        'title' => 'First Title',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $first = syncPrimary($this->event, [
        'title' => 'Second Title',
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => '2026-07-02 11:00:00',
        'timezone' => 'UTC',
    ]);

    expect($first->fresh()->status->getValue())->toBe('rescheduled')
        ->and($first->fresh()->title)->toBe('Second Title')
        ->and($first->fresh()->changeLogs)->toHaveCount(1);

    $second = syncPrimary($this->event, [
        'title' => 'Third Title',
        'starts_at' => '2026-07-03 09:00:00',
        'ends_at' => '2026-07-03 11:00:00',
        'timezone' => 'UTC',
    ]);

    expect($second->fresh()->status->getValue())->toBe('rescheduled')
        ->and($second->fresh()->title)->toBe('Third Title')
        ->and($second->fresh()->starts_at->toDateTimeString())->toBe('2026-07-03 09:00:00')
        ->and($second->fresh()->changeLogs)->toHaveCount(2);
});

it('reschedules a delayed occurrence through the lifecycle with audit', function (): void {
    $occurrence = makeOccurrence($this->event, [
        'status' => 'delayed',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    EventFacade::fake([EventOccurrenceRescheduled::class]);

    $result = syncPrimary($this->event, [
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => '2026-07-02 11:00:00',
        'timezone' => 'UTC',
    ]);

    $fresh = $result->fresh();

    expect($fresh->status->getValue())->toBe('rescheduled')
        ->and($fresh->changeLogs)->toHaveCount(1);

    EventFacade::assertDispatched(EventOccurrenceRescheduled::class);
});

it('rejects schedule changes on live occurrences but allows title updates on identical sync', function (): void {
    makeOccurrence($this->event, [
        'status' => 'live',
        'title' => 'Live Title',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    expect(fn () => syncPrimary($this->event, [
        'starts_at' => '2026-07-02 09:00:00',
        'ends_at' => '2026-07-02 11:00:00',
    ]))->toThrow(InvalidArgumentException::class, 'Live occurrences cannot be rescheduled');

    $result = syncPrimary($this->event, [
        'title' => 'Live Title Updated',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'UTC',
    ]);

    expect($result->fresh()->title)->toBe('Live Title Updated')
        ->and($result->fresh()->status->getValue())->toBe('live')
        ->and($result->fresh()->changeLogs)->toHaveCount(0);
});

it('preserves non-schedule updates on identical terminal re-syncs', function (): void {
    makeOccurrence($this->event, [
        'status' => 'cancelled',
        'title' => 'Old Title',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
    ]);

    $result = syncPrimary($this->event, [
        'title' => 'New Title',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'timezone' => 'UTC',
    ]);

    expect($result->fresh()->title)->toBe('New Title')
        ->and($result->fresh()->status->getValue())->toBe('cancelled');
});

it('rejects a forged event_id bypass in the lifecycle reschedule guard', function (): void {
    $original = config('events.features.owner.enabled');
    config()->set('events.features.owner.enabled', true);

    try {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $occurrenceA = OwnerContext::withOwner($ownerA, function () use ($ownerA) {
            $event = Event::factory()->make(['timezone' => 'UTC']);
            $event->assignOwner($ownerA);
            $event->save();

            return makeOccurrence($event, ['status' => 'published']);
        });

        $eventB = OwnerContext::withOwner($ownerB, function () use ($ownerB) {
            $event = Event::factory()->make(['timezone' => 'UTC']);
            $event->assignOwner($ownerB);
            $event->save();

            return $event;
        });

        $forged = clone $occurrenceA;
        $forged->event_id = $eventB->id;

        OwnerContext::withOwner($ownerB, function () use ($forged): void {
            expect(fn () => app(EventLifecycleWorkflow::class)->reschedule(
                $forged,
                CarbonImmutable::parse('2026-07-02 09:00:00'),
                CarbonImmutable::parse('2026-07-02 11:00:00'),
            ))->toThrow(AuthorizationException::class);
        });

        OwnerContext::withOwner($ownerA, function () use ($occurrenceA): void {
            expect($occurrenceA->fresh()->event_id)->toBe($occurrenceA->event_id)
                ->and($occurrenceA->fresh()->starts_at->toDateTimeString())->toBe('2026-07-01 09:00:00');
        });
    } finally {
        config()->set('events.features.owner.enabled', $original);
    }
});
