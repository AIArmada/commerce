<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('rejects guarded template overrides', function (): void {
    config()->set('events.features.owner.enabled', false);

    $event = Event::factory()->create(['title' => 'Source Event']);
    $template = EventTemplate::factory()->create([
        'template_type' => 'event',
        'payload' => ['title' => 'Source Event'],
        'owner_type' => $event->owner_type,
        'owner_id' => $event->owner_id,
    ]);

    expect(fn () => app(EventTemplateService::class)->createFromTemplate((string) $template->getKey(), [
        'status' => 'published',
    ]))->toThrow(InvalidArgumentException::class, 'Template overrides cannot set [status].');

    expect(fn () => app(EventTemplateService::class)->createFromTemplate((string) $template->getKey(), [
        'owner_id' => 'forged-owner',
    ]))->toThrow(InvalidArgumentException::class, 'Template overrides cannot set [owner_id].');
});

it('applies allowed overrides while ignoring unlisted keys', function (): void {
    config()->set('events.features.owner.enabled', false);

    $event = Event::factory()->create(['title' => 'Source Event']);
    $template = EventTemplate::factory()->create([
        'template_type' => 'event',
        'payload' => ['title' => 'Source Event'],
        'owner_type' => $event->owner_type,
        'owner_id' => $event->owner_id,
    ]);

    $createdId = app(EventTemplateService::class)->createFromTemplate((string) $template->getKey(), [
        'title' => '  Custom   Title  ',
        'status_reason' => 'ignored',
    ]);

    $created = Event::query()->findOrFail($createdId);

    expect($created->title)->toBe('Custom Title');
});

it('does not resolve templates across owners', function (): void {
    $originalOwnerEnabled = config('events.features.owner.enabled');
    $originalOwnerIncludeGlobal = config('events.features.owner.include_global');
    $originalOwnerAutoAssign = config('events.features.owner.auto_assign_on_create');

    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);
    config()->set('events.features.owner.auto_assign_on_create', true);

    try {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $template = OwnerContext::withOwner($ownerA, fn (): EventTemplate => EventTemplate::factory()->create([
            'template_type' => 'event',
            'payload' => ['title' => 'Owned Template'],
        ]));

        expect(function () use ($ownerB, $template): void {
            OwnerContext::withOwner($ownerB, function () use ($template): void {
                app(EventTemplateService::class)->createFromTemplate((string) $template->getKey());
            });
        })->toThrow(ModelNotFoundException::class);
    } finally {
        config()->set('events.features.owner.enabled', $originalOwnerEnabled);
        config()->set('events.features.owner.include_global', $originalOwnerIncludeGlobal);
        config()->set('events.features.owner.auto_assign_on_create', $originalOwnerAutoAssign);
    }
});
