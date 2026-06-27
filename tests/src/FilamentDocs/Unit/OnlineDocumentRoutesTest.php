<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Support\DocRichContentStorage;
use AIArmada\FilamentDocs\Rendering\FilamentRichContentRenderer;
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

    $this->actingAs($user)
        ->get(route('filament-docs.documents.view', ['doc' => $doc->getKey()]))
        ->assertSuccessful()
        ->assertSee('INV-PREVIEW');

    expect($doc->fresh()->pdf_path)->toBeNull();
});

it('renders public share links by hashed token only', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'INV-SHARE',
    ]);

    $shareLink = app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    ));

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertSee('INV-SHARE');

    $shareLink->revoke();

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertNotFound();
});

it('serves public share routes with private no-store headers', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'INV-SHARE-SECURE',
    ]);

    $shareLink = app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
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

    $shareLink = OwnerContext::withOwner($owner, fn () => app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    )));

    $this->get(route('docs.share.show', ['token' => $shareLink->plainToken()]))
        ->assertSuccessful()
        ->assertSee('INV-OWNER-SHARE');

    $accessCount = OwnerContext::withOwner($owner, fn (): int => (int) $shareLink->fresh()?->access_count);

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
