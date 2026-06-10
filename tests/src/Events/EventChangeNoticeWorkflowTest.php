<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\DefaultEventChangeNoticeWorkflow;

it('creates a change notice', function (): void {
    $event = Event::factory()->create();
    $workflow = app(DefaultEventChangeNoticeWorkflow::class);

    $notice = $workflow->create($event, 'title_changed', changedSections: ['title' => true]);

    expect($notice->change_key)->toBe('title_changed');
    expect($notice->status)->toBe('draft');
});

it('creates a people changed notice', function (): void {
    $event = Event::factory()->create();
    $workflow = app(DefaultEventChangeNoticeWorkflow::class);

    $notice = $workflow->peopleChanged($event);

    expect($notice->change_key)->toBe('people_changed');
});

it('creates a schedule changed notice', function (): void {
    $event = Event::factory()->create();
    $workflow = app(DefaultEventChangeNoticeWorkflow::class);

    $notice = $workflow->scheduleChanged($event);

    expect($notice->change_key)->toBe('schedule_changed');
});

it('publishes and retracts a change notice', function (): void {
    $event = Event::factory()->create();
    $workflow = app(DefaultEventChangeNoticeWorkflow::class);
    $notice = $workflow->create($event, 'content_changed', changedSections: ['content' => true]);

    $published = $workflow->publish($notice);
    expect($published->status)->toBe('published');

    $retracted = $workflow->retract($published);
    expect($retracted->status)->toBe('retracted');
});
