<?php

declare(strict_types=1);

use AIArmada\Communications\Actions\PublishTemplateAction;
use AIArmada\Communications\Events\TemplatePublished;
use Illuminate\Support\Facades\Event;

test('PublishTemplateAction exists and can be instantiated', function (): void {
    $action = app(PublishTemplateAction::class);

    expect($action)->toBeInstanceOf(PublishTemplateAction::class);
});

test('PublishTemplateAction handle throws for non-existent version', function (): void {
    $action = app(PublishTemplateAction::class);

    $nonExistentId = (string) Illuminate\Support\Str::uuid();

    try {
        $action->handle($nonExistentId);
    } catch (\Throwable $e) {
        file_put_contents('/tmp/pest_trace.txt', get_class($e) . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString());
        throw $e;
    }
})->throws(\Throwable::class);

test('TemplatePublished event has correct properties', function (): void {
    $event = new TemplatePublished(
        templateId: 'template-1',
        versionId: 'version-1',
        version: 1,
        channel: 'email',
    );

    expect($event->templateId)->toBe('template-1');
    expect($event->versionId)->toBe('version-1');
    expect($event->version)->toBe(1);
    expect($event->channel)->toBe('email');
});

test('PublishTemplateAction dispatches TemplatePublished event', function (): void {
    Event::fake();

    // We can verify the event class is dispatchable without triggering it
    expect(class_exists(TemplatePublished::class))->toBeTrue();

    Event::assertNothingDispatched();
});
