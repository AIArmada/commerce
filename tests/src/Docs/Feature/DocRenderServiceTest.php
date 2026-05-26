<?php

declare(strict_types=1);

use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Support\TemplateBlockRegistry;
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
        [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
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

    expect($shareLink->plainToken)->toBeString()
        ->and($shareLink->token_hash)->not->toBe($shareLink->plainToken)
        ->and($shareLink->allows(ShareLinkAction::View))->toBeTrue()
        ->and($shareLink->allows(ShareLinkAction::Pdf))->toBeFalse();

    $resolved = app(DocRenderService::class)->resolveShareLink($shareLink->plainToken, ShareLinkAction::View);

    expect($resolved->id)->toBe($shareLink->id)
        ->and($resolved->fresh()->access_count)->toBe(1);

    $shareLink->revoke();

    expect(fn (): mixed => app(DocRenderService::class)->resolveShareLink($shareLink->plainToken, ShareLinkAction::View))
        ->toThrow(NotFoundHttpException::class);
});

it('rejects unsupported share link actions', function (): void {
    $doc = Doc::factory()->create();

    expect(fn (): mixed => app(DocRenderService::class)->createShareLink($doc, new ShareLinkData(
        allowedActions: ['view', 'delete'],
    )))->toThrow(InvalidArgumentException::class);
});
