<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Data\PdfRenderOptions;
use AIArmada\Docs\Data\ShareLinkAction;
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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

    $shareLink = app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
    ));

    $plainToken = $shareLink->plainToken();

    expect($plainToken)->toBeString()
        ->and($shareLink->token_hash)->not->toBe($plainToken)
        ->and($shareLink->allows(ShareLinkAction::View))->toBeTrue()
        ->and($shareLink->allows(ShareLinkAction::Pdf))->toBeFalse()
        ->and($shareLink->toArray())->not->toHaveKey('plainToken')
        ->and($shareLink->toJson())->not->toContain($plainToken);

    $resolved = app(DocRenderService::class)->resolveShareLink($plainToken, ShareLinkAction::View);

    expect($resolved->id)->toBe($shareLink->id)
        ->and($resolved->fresh()->access_count)->toBe(1);

    $shareLink->revoke();

    expect(fn (): mixed => app(DocRenderService::class)->resolveShareLink($plainToken, ShareLinkAction::View))
        ->toThrow(NotFoundHttpException::class);
});

it('rejects unsupported share link actions', function (): void {
    $doc = Doc::factory()->create();

    expect(fn (): mixed => app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: ['view', 'delete'],
    )))->toThrow(InvalidArgumentException::class);
});

it('rejects expired share links', function (): void {
    $doc = Doc::factory()->create();
    $shareLink = app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: [ShareLinkAction::View],
        expiresAt: CarbonImmutable::now()->subMinute(),
    ));

    expect(fn (): mixed => app(DocRenderService::class)->resolveShareLink(
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
        expect(fn () => app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
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
