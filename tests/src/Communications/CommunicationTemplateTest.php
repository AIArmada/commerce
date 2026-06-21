<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;

test('creates a template', function (): void {
    $template = CommunicationTemplate::create([
        'key' => 'welcome-email',
        'name' => 'Welcome Email',
        'category' => 'mail',
        'status' => TemplateStatus::Draft,
    ]);

    expect($template->id)->toBeUuid();
    expect($template->key)->toBe('welcome-email');
    expect($template->name)->toBe('Welcome Email');
    expect($template->category)->toBe('mail');
    expect($template->status)->toBeInstanceOf(TemplateStatus::class);
    expect($template->status->value)->toBe('draft');
});

test('template has versions', function (): void {
    $template = CommunicationTemplate::create([
        'key' => 'welcome-email',
        'name' => 'Welcome Email',
        'category' => 'mail',
        'status' => TemplateStatus::Published,
    ]);

    $version = CommunicationTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 1,
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Welcome!',
        'content_text' => 'Hello {{name}}!',
        'checksum' => hash('sha256', 'Hello {{name}}!'),
    ]);

    expect($template->versions)->toHaveCount(1);
    expect($template->versions->first()->id)->toBe($version->id);
    expect($version->version)->toBe(1);
});

test('template versions track full content', function (): void {
    $template = CommunicationTemplate::create([
        'key' => 'welcome-email',
        'name' => 'Welcome Email',
        'category' => 'mail',
        'status' => TemplateStatus::Published,
    ]);

    $version = CommunicationTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 1,
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Welcome!',
        'content_text' => 'Hello {{name}}!',
        'checksum' => hash('sha256', 'Hello {{name}}!'),
        'payload' => ['author' => 'admin'],
    ]);

    expect($version->subject)->toBe('Welcome!');
    expect($version->content_text)->toBe('Hello {{name}}!');
    expect($version->channel)->toBe('mail');
    expect($version->payload['author'])->toBe('admin');
});

test('template cascade deletes versions', function (): void {
    $template = CommunicationTemplate::create([
        'key' => 'test',
        'name' => 'Test',
        'category' => 'mail',
        'status' => TemplateStatus::Draft,
    ]);

    CommunicationTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 1,
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Test',
        'content_text' => 'Body',
        'checksum' => hash('sha256', 'Body'),
    ]);

    expect(CommunicationTemplateVersion::query()->count())->toBe(1);

    $template->delete();

    expect(CommunicationTemplateVersion::query()->count())->toBe(0);
});
