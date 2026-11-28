<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentSharing;
use AIArmada\Docs\Data\ShareLinkAction;
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocShareLink;
use AIArmada\Docs\Support\DocRichContentStorage;
use AIArmada\FilamentDocs\Rendering\FilamentRichContentRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(TestCase::class);

it('renders authenticated online previews without an existing pdf', function (): void {
    config()->set('docs.owner.enabled', false);

    $user = User::query()->create([
        'name' => 'Preview User',
        'email' => 'preview-user@example.test',
        'password' => bcrypt('password'),
    ]);

    $doc = Doc::factory()->create([
        'doc_number' => 'INV-PREVIEW',
        'pdf_path' => null,
    ]);

    Gate::define('document.view', static fn (User $actor): bool => $actor->is($user));

    $this->actingAs($user)
        ->get(route('filament-docs.documents.view', ['doc' => $doc->getKey()]))
        ->assertSuccessful()
        ->assertSee('INV-PREVIEW')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    expect($doc->fresh()->pdf_path)->toBeNull();
});

it('denies authenticated document routes without document view permission', function (): void {
    config()->set('docs.owner.enabled', false);

    $user = User::factory()->create();
    $doc = Doc::factory()->create(['pdf_path' => null]);

    Gate::define('document.view', static fn (): bool => false);
    Gate::define('purchase.view', static fn (): bool => true);

    $this->actingAs($user)
        ->get(route('filament-docs.documents.view', ['doc' => $doc->getKey()]))
        ->assertForbidden();

    $this->get(route('filament-docs.download', ['doc' => $doc->getKey()]))
        ->assertForbidden();

    expect($doc->fresh()->pdf_path)->toBeNull();
});

it('renders public share links by hashed token only', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'INV-SHARE',
    ]);

    $shareLink = app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    ));

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertSee('INV-SHARE');

    app(DocumentSharing::class)->revokeAll((string) $doc->getKey());

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertNotFound();
});

it('serves public share routes with private no-store headers', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'INV-SHARE-SECURE',
    ]);

    $shareLink = app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: [ShareLinkAction::View, ShareLinkAction::Pdf],
    ));

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->once()->andReturn($pdfBuilderMock);

    $this->get(route('docs.share.pdf', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('renders owner scoped public share links without ambient owner context', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Share Owner',
        'email' => 'share-owner@example.test',
        'password' => bcrypt('password'),
    ]);

    $doc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create([
        'doc_number' => 'INV-OWNER-SHARE',
    ]));

    $shareLink = OwnerContext::withOwner($owner, fn () => app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    )));

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertSee('INV-OWNER-SHARE');

    $accessCount = OwnerContext::withOwner($owner, fn (): int => (int) DocShareLink::query()->findOrFail($shareLink->id)->access_count);

    expect($accessCount)->toBe(1);
});

it('does not render rich content attachment ids outside the current owner directory', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.storage.disk', 'local');
    config()->set('docs.storage.rich_content_visibility', 'public');

    Storage::fake('local');

    $ownerA = User::query()->create([
        'name' => 'Attachment Owner A',
        'email' => 'attachment-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::query()->create([
        'name' => 'Attachment Owner B',
        'email' => 'attachment-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownerBPath = OwnerContext::withOwner($ownerB, fn (): string => DocRichContentStorage::directory() . '/secret.png');

    Storage::disk('local')->put($ownerBPath, 'not-an-image');

    $html = OwnerContext::withOwner($ownerA, fn (): string => app(FilamentRichContentRenderer::class)->render([
        'type' => 'doc',
        'content' => [
            [
                'type' => 'image',
                'attrs' => ['id' => $ownerBPath],
            ],
        ],
    ])->toHtml());

    expect($html)
        ->not->toContain($ownerBPath)
        ->not->toContain('secret.png');
});

it('rejects unsafe raw image and link URLs in the Filament renderer', function (string $url): void {
    $html = app(FilamentRichContentRenderer::class)->render([
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['src' => $url]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Visible text', 'marks' => [
                    ['type' => 'link', 'attrs' => ['href' => $url]],
                ]],
            ]],
        ],
    ])->toHtml();

    expect($html)->toContain('Visible text')->not->toContain('<img', 'href=');
})->with([
    'private address' => 'http://127.0.0.1/image.png',
    'backslash authority' => '/\\example.test/image.png',
    'control character' => "/\t/example.test/image.png",
    'protocol-relative' => '//example.test/image.png',
    'non-HTTP scheme' => 'javascript:alert(1)',
]);

it('resolves authorized attachment ids without trusting a supplied source URL', function (): void {
    config()->set('docs.owner.enabled', false);
    config()->set('docs.storage.disk', 'local');
    config()->set('docs.storage.rich_content_visibility', 'public');
    Storage::fake('local');

    $path = DocRichContentStorage::directory() . '/allowed.png';
    Storage::disk('local')->put($path, 'image');

    $html = app(FilamentRichContentRenderer::class)->render([
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['id' => $path, 'src' => 'http://127.0.0.1/secret']],
            ['type' => 'image', 'attrs' => ['id' => DocRichContentStorage::directory() . '/missing.png', 'src' => '/fallback.png']],
        ],
    ])->toHtml();

    expect($html)->toContain('allowed.png')->not->toContain('127.0.0.1', 'fallback.png', 'missing.png');
});
