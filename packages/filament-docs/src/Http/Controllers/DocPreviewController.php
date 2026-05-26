<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Http\Controllers;

use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Symfony\Component\HttpFoundation\Response;

final class DocPreviewController
{
    public function __invoke(Doc | string $doc, DocRenderService $renderer): Response
    {
        if (! $doc instanceof Doc) {
            $doc = Doc::query()->findOrFail($doc);
        }

        DocsOwnerScope::assertCanAccessDoc($doc);

        return response($renderer->renderHtml($doc, RenderAudience::AdminPreview)->toHtml());
    }
}
