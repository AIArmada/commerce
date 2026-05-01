<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Http\Controllers\DocTrackingController;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Services\DocEmailService;

test('click tracking rejects non-http redirect schemes', function (): void {
    config()->set('app.url', 'https://app.example.test');

    $doc = Doc::factory()->create();

    $email = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'customer@example.test',
        'subject' => 'Tracked link test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $service = app(DocEmailService::class);
    $trackedUrl = $service->getTrackedLinkUrl($email, 'javascript:alert(1)');

    $response = app(DocTrackingController::class)->click(tokenFromTrackedUrl($trackedUrl));

    expect($response->getTargetUrl())->toBe('https://app.example.test');
});

test('click tracking allows safe absolute http urls', function (): void {
    config()->set('app.url', 'https://app.example.test');

    $doc = Doc::factory()->create();

    $email = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'customer@example.test',
        'subject' => 'Tracked link test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $service = app(DocEmailService::class);
    $trackedUrl = $service->getTrackedLinkUrl($email, 'https://example.test/docs/123');

    $response = app(DocTrackingController::class)->click(tokenFromTrackedUrl($trackedUrl));

    expect($response->getTargetUrl())->toBe('https://example.test/docs/123');
});

test('click tracking allows safe relative paths', function (): void {
    config()->set('app.url', 'https://app.example.test');

    $doc = Doc::factory()->create();

    $email = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'customer@example.test',
        'subject' => 'Tracked link test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $service = app(DocEmailService::class);
    $trackedUrl = $service->getTrackedLinkUrl($email, '/portal/invoices/abc');

    $response = app(DocTrackingController::class)->click(tokenFromTrackedUrl($trackedUrl));

    expect($response->getTargetUrl())->toBe('http://localhost/portal/invoices/abc');
});

function tokenFromTrackedUrl(string $trackedUrl): string
{
    $path = parse_url($trackedUrl, PHP_URL_PATH);

    expect($path)->toBeString();

    return basename((string) $path);
}
