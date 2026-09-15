<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\PublishTemplateAction;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Events\TemplatePublished;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

test('PublishTemplateAction handle throws for non-existent version', function (): void {
    $action = app(PublishTemplateAction::class);

    $nonExistentId = (string) Str::uuid();

    $action->handle($nonExistentId);
})->throws(ModelNotFoundException::class);

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

    [$template, $version] = OwnerContext::withOwner(null, function (): array {
        $template = (new CommunicationTemplate)->forceFill([
            'key' => 'publish-event',
            'name' => 'Publish Event',
            'category' => 'mail',
            'status' => TemplateStatus::Draft,
        ]);
        $template->save();

        $version = CommunicationTemplateVersion::create([
            'template_id' => $template->id,
            'version' => 1,
            'channel' => 'mail',
            'locale' => 'en',
            'subject' => 'Hello',
            'content_text' => 'Hello {{name}}!',
            'checksum' => hash('sha256', 'Hello {{name}}!'),
        ]);

        app(PublishTemplateAction::class)->handle($version->id);

        return [$template, $version];
    });

    Event::assertDispatched(TemplatePublished::class, fn (TemplatePublished $event): bool => $event->templateId === $template->id
        && $event->versionId === $version->id
        && $event->version === 1
        && $event->channel === 'mail');
});
