<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Actions\ManageEventAction;
use AIArmada\Events\Features\Catalog\Actions\ManageEventTemplateAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('protects event creation and edit lifecycle fields', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $outsider): void {
        $action = app(ManageEventAction::class);
        $data = ['title' => '  Launch   Event ', 'slug' => 'launch-event'];

        expect(fn () => $action->save(null, [...$data, 'status' => 'published'], $owner))->toThrow(ValidationException::class);

        $event = $action->save(null, $data, $owner);

        expect($event->title)->toBe('Launch Event')
            ->and($event->status->getValue())->toBe('draft');

        expect(fn () => $action->save($event->id, ['title' => 'Unauthorized'], $outsider))->toThrow(AuthorizationException::class);

        $event = $action->save($event->id, ['title' => 'Updated Event', 'status' => 'published', 'owner_id' => $outsider->getKey()], $owner);

        expect($event->title)->toBe('Updated Event')
            ->and($event->status->getValue())->toBe('draft')
            ->and($event->owner_id)->toBe((string) $owner->getKey());
    });
});

it('preserves template payload and type and records publication timestamps', function (): void {
    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $action = app(ManageEventTemplateAction::class);
        $template = $action->save(null, ['name' => 'Launch', 'template_type' => 'event', 'status' => 'draft'], $owner);
        $template = $action->save($template->id, ['status' => 'published', 'template_type' => 'session', 'payload' => ['forged' => true]], $owner);

        expect($template->template_type)->toBe('event')
            ->and($template->payload)->toBe([])
            ->and($template->published_at)->not->toBeNull();

        $template = $action->save($template->id, ['status' => 'archived'], $owner);

        expect($template->archived_at)->not->toBeNull();
    });
});
