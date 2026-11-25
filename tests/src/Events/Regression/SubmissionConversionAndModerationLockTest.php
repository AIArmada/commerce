<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('rejects converting the same submission twice', function (): void {
    $owner = User::factory()->create();

    $submission = EventSubmission::query()->create([
        'submitter_type' => $owner->getMorphClass(),
        'submitter_id' => $owner->getKey(),
        'target_type' => $owner->getMorphClass(),
        'target_id' => $owner->getKey(),
        'submission_data' => ['title' => 'Race Lecture', 'summary' => ''],
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $converter = app(EventSubmissionConverter::class);
    $eventId = $converter->convert((string) $submission->getKey());

    expect(Event::query()->findOrFail($eventId)->title)->toBe('Race Lecture');

    expect(fn () => $converter->convert((string) $submission->getKey()))
        ->toThrow(RuntimeException::class, 'has already been converted');
});

it('locks moderation transitions so a request is only approved once', function (): void {
    $owner = User::factory()->create();

    $submission = EventSubmission::query()->create([
        'submitter_type' => $owner->getMorphClass(),
        'submitter_id' => $owner->getKey(),
        'target_type' => $owner->getMorphClass(),
        'target_id' => $owner->getKey(),
        'submission_data' => ['title' => 'Moderated Lecture'],
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $workflow = app(EventModerationWorkflow::class);
    $workflow->approve((string) $submission->getKey(), $owner);

    expect($submission->fresh()->status->getValue())->toBe('approved');

    expect(fn () => $workflow->approve((string) $submission->getKey(), $owner))
        ->toThrow(TransitionNotFound::class);
});

it('refuses moderation when the attached event no longer exists', function (): void {
    $owner = User::factory()->create();
    $event = Event::factory()->create();

    $submission = EventSubmission::query()->create([
        'submitter_type' => $owner->getMorphClass(),
        'submitter_id' => $owner->getKey(),
        'target_type' => $owner->getMorphClass(),
        'target_id' => $owner->getKey(),
        'event_id' => $event->id,
        'submission_data' => ['title' => 'Orphaned Lecture'],
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    DB::table($event->getTable())->where($event->getKeyName(), $event->getKey())->delete();

    expect(fn () => app(EventModerationWorkflow::class)->approve((string) $submission->getKey(), $owner))
        ->toThrow(ModelNotFoundException::class);
});
