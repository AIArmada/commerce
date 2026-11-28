<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

use AIArmada\Docs\Data\PdfRenderOptions;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Runtime rendering boundary for document consumers. */
interface DocumentRendering
{
    public function renderHtml(Doc $doc, RenderAudience $audience): HtmlString;

    public function renderPdf(Doc $doc): string;

    /** @return array{string, PdfRenderOptions, string} */
    public function pdfInput(Doc $doc): array;

    /**
     * @param array<string, mixed>|null $body
     * @param array<int, array<string, mixed>> $items
     */
    public function validateDocPayload(Model $template, ?array $body, array $items): void;
}
