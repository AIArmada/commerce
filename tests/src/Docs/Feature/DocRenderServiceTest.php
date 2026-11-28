<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentSharing;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Data\PdfRenderOptions;
use AIArmada\Docs\Data\ShareLinkAction;
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Features\Delivery\Actions\ManageDocShareLinks;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocShareLink;
use AIArmada\Docs\Features\Templates\Models\DocTemplate;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('renders tiptap json body with merge tags and sanitized text', function (): void {
    $template = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'layout' => [
            ['type' => DocTemplateBlockType::RichBody->value, 'data' => []],
        ],
    ]);

    $doc = Doc::factory()->create([
        'doc_template_id' => $template->id,
        'doc_number' => 'INV-JSON',
        'customer_data' => ['name' => 'Acme Corp', 'email' => 'billing@example.test'],
        'body' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello '],
                        ['type' => 'mergeTag', 'attrs' => ['id' => 'customer_name']],
                        ['type' => 'text', 'text' => ' <script>alert(1)</script>'],
                    ],
                ],
            ],
        ],
    ]);

    $html = app(DocRenderService::class)->renderHtml($doc, RenderAudience::AdminPreview)->toHtml();

    expect($html)
        ->toContain('Acme Corp')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

it('validates declarative template blocks', function (): void {
    expect(fn (): mixed => TemplateBlockRegistry::assertValid([
        ['type' => 'unsupported', 'data' => []],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): mixed => TemplateBlockRegistry::assertValid([
        'not-a-block',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): mixed => TemplateBlockRegistry::assertValid([
        ['type' => DocTemplateBlockType::DocumentHeader->value, 'data' => ['view' => 'docs::unsafe']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects document body when the selected template has no body slot', function (): void {
    $template = DocTemplate::factory()->create([
        'layout' => [
            ['type' => DocTemplateBlockType::LineItems->value, 'data' => []],
        ],
    ]);

    expect(fn (): mixed => app(DocRenderService::class)->validateDocPayload(
        $template,
        ['type' => 'doc', 'content' => []],
        [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
    ))->toThrow(ValidationException::class);
});

it('does not require items for hidden line item blocks', function (): void {
    $template = DocTemplate::factory()->create([
        'layout' => [
            ['type' => DocTemplateBlockType::LineItems->value, 'data' => ['visible' => false]],
        ],
    ]);

    app(DocRenderService::class)->validateDocPayload($template, null, []);

    expect(true)->toBeTrue();
});

it('creates revocable action-limited share links', function (): void {
    $doc = Doc::factory()->create();

    $shareLink = app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    ));

    $plainToken = $shareLink->plainToken();

    $stored = DocShareLink::query()->findOrFail($shareLink->id);

    expect($plainToken)->toBeString()
        ->and($stored->token_hash)->not->toBe($plainToken)
        ->and($stored->allows(ShareLinkAction::View))->toBeTrue()
        ->and($stored->allows(ShareLinkAction::Pdf))->toBeFalse()
        ->and($stored->toArray())->not->toHaveKey('plainToken')
        ->and($stored->toJson())->not->toContain($plainToken);

    $resolved = app(ManageDocShareLinks::class)->resolve($plainToken, ShareLinkAction::View);

    expect($resolved->id)->toBe($shareLink->id)
        ->and($resolved->fresh()->access_count)->toBe(1);

    app(DocumentSharing::class)->revokeAll((string) $doc->getKey());

    expect(fn (): mixed => app(ManageDocShareLinks::class)->resolve($plainToken, ShareLinkAction::View))
        ->toThrow(NotFoundHttpException::class);
});

it('rejects unsupported share link actions', function (): void {
    $doc = Doc::factory()->create();

    expect(fn (): mixed => app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: ['view', 'delete'],
    )))->toThrow(InvalidArgumentException::class);
});

it('rejects expired share links', function (): void {
    $doc = Doc::factory()->create();
    $shareLink = app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
        expiresAt: CarbonImmutable::now()->subMinute(),
    ));

    expect(fn (): mixed => app(ManageDocShareLinks::class)->resolve(
        $shareLink->plainToken(),
        ShareLinkAction::View,
    ))->toThrow(NotFoundHttpException::class);
});

it('captures immutable share expiration dates', function (): void {
    $date = new DateTimeImmutable('2026-11-28T00:00:00+00:00');
    $data = new ShareLinkData(expiresAt: $date);

    $date = $date->modify('+1 day');
    expect($date->format('Y-m-d'))->toBe('2026-11-29');

    expect($data->expiresAt?->format('Y-m-d'))->toBe('2026-11-28');
});

it('does not issue a share link for a document owned by another tenant', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', true);
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $doc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create());

    OwnerContext::withOwner($other, function () use ($doc): void {
        expect(fn () => app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData(
            allowedActions: [ShareLinkAction::View],
        )))->toThrow(AuthorizationException::class);
    });
});

it('delegates PDF generation to a replaceable driver with normalized options', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'DRIVER-TEST',
        'metadata' => ['pdf' => ['format' => 'a5', 'orientation' => 'landscape', 'margin' => ['top' => 500]]],
    ]);

    $driver = Mockery::mock(PdfRenderer::class);
    $driver->shouldReceive('render')->once()->withArgs(
        fn (string $html, PdfRenderOptions $options): bool => str_contains($html, 'DRIVER-TEST')
            && $options->format === 'a5'
            && $options->orientation === 'landscape'
            && $options->marginTop === 100,
    )->andReturn('custom PDF');
    app()->instance(PdfRenderer::class, $driver);
    app()->forgetInstance(DocRenderService::class);

    expect(app(DocRenderService::class)->renderPdf($doc))->toBe('custom PDF');
});

it('reuses identical PDF inputs and regenerates changed document and template content', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');
    $template = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'layout' => [['type' => DocTemplateBlockType::NotesTerms->value, 'data' => []]],
    ]);
    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_template_id' => $template->id,
        'notes' => 'Original notes',
    ]);

    $driver = Mockery::mock(PdfRenderer::class);
    $driver->shouldReceive('render')->times(3)->andReturn('original', 'edited', 'template changed');
    app()->instance(PdfRenderer::class, $driver);
    app()->forgetInstance(DocRenderService::class);
    $renderer = app(DocRenderService::class);

    $path = $renderer->storePdf($doc);
    expect($renderer->storePdf($doc))->toBe($path);
    expect(Storage::disk('local')->get($path))->toBe('original');

    $doc->update(['notes' => 'Edited notes']);
    expect($renderer->storePdf($doc))->toBe($path);
    expect(Storage::disk('local')->get($path))->toBe('edited');

    $template->update(['layout' => [['type' => DocTemplateBlockType::DocumentHeader->value, 'data' => []]]]);
    expect($renderer->storePdf($doc))->toBe($path);
    expect(Storage::disk('local')->get($path))->toBe('template changed');
});

it('retries PDF publication if document content changes while the driver is rendering', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');
    $template = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'layout' => [['type' => DocTemplateBlockType::NotesTerms->value, 'data' => []]],
    ]);
    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_template_id' => $template->id,
        'notes' => 'Before render',
    ]);

    $driver = Mockery::mock(PdfRenderer::class);
    $driver->shouldReceive('render')->once()->withArgs(
        fn (string $html, PdfRenderOptions $options): bool => str_contains($html, 'Before render'),
    )->andReturnUsing(function () use ($doc): string {
        $doc->update(['notes' => 'After render began']);

        return 'stale PDF';
    });
    $driver->shouldReceive('render')->once()->withArgs(
        fn (string $html, PdfRenderOptions $options): bool => str_contains($html, 'After render began'),
    )->andReturn('fresh PDF');

    app()->instance(PdfRenderer::class, $driver);
    app()->forgetInstance(DocRenderService::class);
    $renderer = app(DocRenderService::class);

    $path = $renderer->storePdf($doc);

    expect(Storage::disk('local')->get($path))->toBe('fresh PDF')
        ->and($renderer->storePdf($doc))->toBe($path);
});

it('rejects ambiguous relative rich-content URLs before rendering links and images', function (string $url): void {
    $renderer = new \AIArmada\Docs\Rendering\TiptapJsonRenderer;

    $html = $renderer->render([
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['src' => $url]],
            ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => $url]]]],
        ],
    ])->toHtml();

    expect($html)->toBe('Link');
})->with([
    'backslash authority' => '/\\example.test/image.png',
    'tab in authority' => "/\t/example.test/image.png",
    'newline in authority' => "/\n/example.test/image.png",
    'space in path' => '/images/my image.png',
    'protocol-relative' => '//example.test/image.png',
]);

it('preserves unambiguous root-relative rich-content links and images', function (): void {
    $renderer = new \AIArmada\Docs\Rendering\TiptapJsonRenderer;

    $html = $renderer->render([
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['src' => '/images/my%20image.png']],
            ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/documents/123']]]],
        ],
    ])->toHtml();

    expect($html)->toContain('src="/images/my%20image.png"', 'href="/documents/123"');
});
