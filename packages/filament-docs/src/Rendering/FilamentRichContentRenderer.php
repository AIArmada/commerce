<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Rendering;

use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\Support\DocRichContentStorage;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;

final class FilamentRichContentRenderer implements RichContentRendererInterface
{
    /**
     * @param  array<string, mixed>|null  $content
     * @param  array<string, mixed>  $mergeTags
     */
    public function render(?array $content, array $mergeTags = []): HtmlString
    {
        if ($content === null || $content === []) {
            return new HtmlString('');
        }

        $renderer = RichContentRenderer::make($content)
            ->mergeTags($mergeTags)
            ->fileAttachmentProvider(new DocsRichContentFileAttachmentProvider)
            ->fileAttachmentsDisk((string) config('docs.storage.disk', 'local'))
            ->fileAttachmentsVisibility((string) config('docs.storage.rich_content_visibility', 'private'))
            ->processNodesUsing(static function (object &$node): void {
                if (($node->type ?? null) !== 'image') {
                    return;
                }

                $id = $node->attrs->id ?? null;

                if ($id !== null && ! DocRichContentStorage::isAllowedFileId($id)) {
                    unset($node->attrs->src);
                }

                unset($node->attrs->id);
            });

        return new HtmlString($renderer->toHtml());
    }
}
