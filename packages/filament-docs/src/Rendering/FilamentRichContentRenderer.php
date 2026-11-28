<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Rendering;

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\Support\RichContentUrlGuard;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

final class FilamentRichContentRenderer implements RichContentRendererInterface
{
    public function __construct(
        private readonly PublicHttpUrlGuard $urlGuard = new PublicHttpUrlGuard,
    ) {}

    /**
     * @param  array<string, mixed>|null  $content
     * @param  array<string, mixed>  $mergeTags
     */
    public function render(?array $content, array $mergeTags = []): HtmlString
    {
        if ($content === null || $content === []) {
            return new HtmlString('');
        }

        $count = 0;
        $content = $this->sanitizeNode($content, new DocsRichContentFileAttachmentProvider, $count);

        if ($content === null) {
            return new HtmlString('');
        }

        return new HtmlString(RichContentRenderer::make($content)->mergeTags($mergeTags)->toHtml());
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function sanitizeNode(array $node, DocsRichContentFileAttachmentProvider $attachments, int &$count, int $depth = 0): ?array
    {
        if ($depth > 32 || ++$count > 2000) {
            throw new InvalidArgumentException('Rich content exceeds the maximum nesting depth or node count.');
        }

        if (($node['type'] ?? null) === 'image') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $id = $attrs['id'] ?? null;

            if ($id !== null) {
                $url = $attachments->getFileAttachmentUrl($id);

                if ($url === null) {
                    return null;
                }

                $attrs['src'] = $url;
                unset($attrs['id']);
            } elseif (! RichContentUrlGuard::allows($attrs['src'] ?? null, $this->urlGuard)) {
                return null;
            }

            $node['attrs'] = $attrs;
        }

        if (is_array($node['marks'] ?? null)) {
            $node['marks'] = array_values(array_filter($node['marks'], fn (mixed $mark): bool => is_array($mark)
                && (($mark['type'] ?? null) !== 'link' || RichContentUrlGuard::allows(data_get($mark, 'attrs.href'), $this->urlGuard))));
        }

        if (is_array($node['content'] ?? null)) {
            $children = [];

            foreach ($node['content'] as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $sanitized = $this->sanitizeNode($child, $attachments, $count, $depth + 1);

                if ($sanitized !== null) {
                    $children[] = $sanitized;
                }
            }

            $node['content'] = $children;
        }

        return $node;
    }
}
