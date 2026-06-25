<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventReport;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSeriesRule;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Models\EventSubmissionAttachment;
use AIArmada\Events\Models\EventTemplate;
use AIArmada\Events\Models\EventTemplateItem;
use Illuminate\Auth\Access\AuthorizationException;

it('isolates event reads and writes by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Event Owner A',
        'email' => 'event-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Event Owner B',
        'email' => 'event-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $eventA = OwnerContext::withOwner($ownerA, function (): Event {
        return Event::factory()->create();
    });

    $eventB = OwnerContext::withOwner($ownerB, function (): Event {
        return Event::factory()->create();
    });

    $ownerAEventIds = OwnerContext::withOwner($ownerA, function (): array {
        return Event::query()->pluck('id')->all();
    });

    expect($ownerAEventIds)->toEqual([$eventA->id]);

    expect(function () use ($ownerA, $eventB): void {
        OwnerContext::withOwner($ownerA, function () use ($eventB): void {
            OwnerWriteGuard::findOrFailForOwner(Event::class, $eventB->id);
        });
    })->toThrow(AuthorizationException::class);
});

it('isolates event-bound child reads and writes by owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$eventA, $policyA, $registrationA] = OwnerContext::withOwner($ownerA, function (): array {
        $event = Event::factory()->create();

        return [
            $event,
            EventAccessPolicy::factory()->create(['event_id' => $event->id]),
            EventRegistration::factory()->create(['event_id' => $event->id]),
        ];
    });

    [$eventB, $policyB, $occurrenceB, $registrationB] = OwnerContext::withOwner($ownerB, function (): array {
        $event = Event::factory()->create();

        return [
            $event,
            EventAccessPolicy::factory()->create(['event_id' => $event->id]),
            EventOccurrence::factory()->create(['event_id' => $event->id]),
            EventRegistration::factory()->create(['event_id' => $event->id]),
        ];
    });

    OwnerContext::withOwner($ownerA, function () use ($eventA, $eventB, $occurrenceB, $policyA, $policyB, $registrationA, $registrationB): void {
        expect(EventRegistration::query()->pluck('id')->all())->toBe([$registrationA->id])
            ->and(EventAccessPolicy::query()->pluck('id')->all())->toBe([$policyA->id])
            ->and(EventAccessPolicy::query()->whereKey($policyB)->exists())->toBeFalse()
            ->and(Event::metadataValue('event.name', slug: $eventA->slug))->toBe($eventA->title)
            ->and(Event::metadataValue('event.name', slug: $eventB->slug))->toBeNull();

        expect(fn () => app(RegistrationServiceInterface::class)->cancel($registrationB))
            ->toThrow(AuthorizationException::class);

        expect(fn () => app(EventLifecycleWorkflow::class)->complete($occurrenceB))
            ->toThrow(AuthorizationException::class);

        expect(fn () => EventRegistration::query()->create([
            'event_id' => $eventB->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'currency' => 'MYR',
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => EventAccessPolicy::query()->create([
            'event_id' => $eventB->id,
        ]))->toThrow(AuthorizationException::class);

        DispatchEventChangeChainAction::run(
            eventId: $eventA->id,
            changeType: 'published',
        );

        expect($eventA->changeLogs()->count())->toBe(1);
    });
});

it('isolates polymorphic, submission, series, and template records by owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$event, $submission, $attachment, $assignmentRequest, $report, $seriesRule, $templateItem] = OwnerContext::withOwner(
        $ownerA,
        function () use ($ownerA): array {
            $event = Event::factory()->create();
            $submission = EventSubmission::factory()->create([
                'target_type' => $ownerA->getMorphClass(),
                'target_id' => $ownerA->getKey(),
            ]);
            $series = EventSeries::factory()->create();
            $template = EventTemplate::factory()->create();

            return [
                $event,
                $submission,
                EventSubmissionAttachment::factory()->create([
                    'event_submission_id' => $submission->id,
                ]),
                app(SubmitAssignmentRequestAction::class)->handle(
                    $ownerA,
                    $ownerA,
                    'Manage my events.',
                ),
                EventReport::factory()->create([
                    'reportable_type' => $event->getMorphClass(),
                    'reportable_id' => $event->getKey(),
                    'event_id' => $event->id,
                ]),
                EventSeriesRule::factory()->create([
                    'event_series_id' => $series->id,
                ]),
                EventTemplateItem::factory()->create([
                    'event_template_id' => $template->id,
                ]),
            ];
        },
    );

    OwnerContext::withOwner($ownerA, function () use ($event, $ownerB, $submission): void {
        expect(EventSubmission::query()->count())->toBe(1)
            ->and(EventSubmissionAttachment::query()->count())->toBe(1)
            ->and(EventManagementAssignmentRequest::query()->count())->toBe(1)
            ->and(EventReport::query()->count())->toBe(1)
            ->and(EventSeriesRule::query()->count())->toBe(1)
            ->and(EventTemplateItem::query()->count())->toBe(1);

        expect(function () use ($event, $ownerB, $submission): void {
            $submission->target_type = $ownerB->getMorphClass();
            $submission->target_id = $ownerB->getKey();
            $submission->event_id = $event->id;
            $submission->save();
        })->toThrow(InvalidArgumentException::class);

        $submission->refresh();
    });

    OwnerContext::withOwner($ownerB, function () use ($assignmentRequest, $attachment, $report, $seriesRule, $submission, $templateItem): void {
        expect(EventSubmission::query()->count())->toBe(0)
            ->and(EventSubmissionAttachment::query()->count())->toBe(0)
            ->and(EventManagementAssignmentRequest::query()->count())->toBe(0)
            ->and(EventReport::query()->count())->toBe(0)
            ->and(EventSeriesRule::query()->count())->toBe(0)
            ->and(EventTemplateItem::query()->count())->toBe(0);

        foreach ([$submission, $attachment, $assignmentRequest, $report, $seriesRule, $templateItem] as $model) {
            expect(function () use ($model): void {
                $model->metadata = ['cross_owner_write' => true];
                $model->save();
            })->toThrow(AuthorizationException::class);
        }
    });
});
