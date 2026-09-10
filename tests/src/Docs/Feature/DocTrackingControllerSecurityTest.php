<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($response->getTargetUrl())->toBe('https://app.example.test')
        ->and($cacheControl)->toContain('no-store')
        ->and($cacheControl)->toContain('no-cache')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
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

    expect($response->getTargetUrl())->toBe(app('url')->to('/portal/invoices/abc'));
});

function tokenFromTrackedUrl(string $trackedUrl): string
{
    $path = parse_url($trackedUrl, PHP_URL_PATH);

    expect($path)->toBeString();

    return basename((string) $path);
}

test('open tracking returns a one-pixel non-cacheable response', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'customer@example.test',
        'subject' => 'Tracked pixel test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $trackedUrl = app(DocEmailService::class)->getTrackingPixelUrl($email);
    $response = app(DocTrackingController::class)->open(tokenFromTrackedUrl($trackedUrl));
    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('image/gif')
        ->and($cacheControl)->toContain('no-store')
        ->and($cacheControl)->toContain('no-cache')
        ->and($response->getContent())->not->toBeEmpty();
});

test('owner scoped tracking writes re-enter the email owner context', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Tracking Owner',
        'email' => 'tracking-owner-' . uniqid() . '@example.test',
        'password' => 'secret',
    ]);

    [$email, $trackingUrl] = OwnerContext::withOwner($owner, function (): array {
        $doc = Doc::factory()->create();
        $email = DocEmail::query()->create([
            'doc_id' => $doc->id,
            'recipient_email' => 'customer@example.test',
            'subject' => 'Owner scoped tracking test',
            'body' => 'Body',
            'status' => EmailStatus::Sent,
        ]);

        return [$email, app(DocEmailService::class)->getTrackingPixelUrl($email)];
    });

    $response = app(DocTrackingController::class)->open(tokenFromTrackedUrl($trackingUrl));

    expect($response->getStatusCode())->toBe(200)
        ->and(OwnerContext::withOwner($owner, fn (): int => (int) $email->fresh()->open_count))->toBe(1);
});
