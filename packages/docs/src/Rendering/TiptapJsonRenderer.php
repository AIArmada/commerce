<?php

declare(strict_types=1);

namespace AIArmada\Docs\Rendering;

use AIArmada\Docs\Contracts\RichContentRendererInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class TiptapJsonRenderer implements RichContentRendererInterface
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

        return new HtmlString($this->renderNode($content, $mergeTags));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $mergeTags
     */
    private function renderNode(array $node, array $mergeTags): string
    {
        $type = (string) ($node['type'] ?? 'doc');

        if ($type === 'text') {
            return $this->renderText($node, $mergeTags);
        }

        $children = $this->renderChildren($node, $mergeTags);

        return match ($type) {
            'doc' => $children,
            'paragraph' => "<p>{$children}</p>",
            'heading' => $this->wrapHeading($node, $children),
            'hardBreak' => '<br>',
            'bulletList' => "<ul>{$children}</ul>",
            'orderedList' => "<ol>{$children}</ol>",
            'listItem' => "<li>{$children}</li>",
            'blockquote' => "<blockquote>{$children}</blockquote>",
            'horizontalRule' => '<hr>',
            'table' => "<table>{$children}</table>",
            'tableRow' => "<tr>{$children}</tr>",
            'tableHeader' => '<th' . $this->tableAttributes($node) . ">{$children}</th>",
            'tableCell' => '<td' . $this->tableAttributes($node) . ">{$children}</td>",
            'image' => $this->renderImage($node),
            'mergeTag' => e((string) ($mergeTags[(string) data_get($node, 'attrs.id', '')] ?? '')),
            default => $children,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $mergeTags
     */
    private function renderChildren(array $node, array $mergeTags): string
    {
        $children = Arr::wrap($node['content'] ?? []);

        return collect($children)
            ->filter(static fn (mixed $child): bool => is_array($child))
            ->map(fn (array $child): string => $this->renderNode($child, $mergeTags))
            ->implode('');
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $mergeTags
     */
    private function renderText(array $node, array $mergeTags): string
    {
        $text = e((string) ($node['text'] ?? ''));
        $text = $this->replaceMergeTags($text, $mergeTags);

        foreach (Arr::wrap($node['marks'] ?? []) as $mark) {
            if (! is_array($mark)) {
                continue;
            }

            $text = $this->applyMark($text, $mark);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $mergeTags
     */
    private function replaceMergeTags(string $text, array $mergeTags): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',
            static fn (array $matches): string => e((string) ($mergeTags[$matches[1]] ?? '')),
            $text,
        );
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private function applyMark(string $text, array $mark): string
    {
        $type = (string) ($mark['type'] ?? '');

        return match ($type) {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'underline' => "<u>{$text}</u>",
            'strike' => "<s>{$text}</s>",
            'code' => "<code>{$text}</code>",
            'link' => $this->wrapLink($text, $mark),
            default => $text,
        };
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private function wrapLink(string $text, array $mark): string
    {
        $href = (string) data_get($mark, 'attrs.href', '');

        if (! $this->isSafeUrl($href)) {
            return $text;
        }

        return '<a href="' . e($href) . '" rel="noopener noreferrer">' . $text . '</a>';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function wrapHeading(array $node, string $children): string
    {
        $level = (int) data_get($node, 'attrs.level', 2);
        $level = max(1, min(6, $level));

        return "<h{$level}>{$children}</h{$level}>";
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderImage(array $node): string
    {
        $src = (string) data_get($node, 'attrs.src', '');

        if (! $this->isSafeUrl($src)) {
            return '';
        }

        $alt = e((string) data_get($node, 'attrs.alt', ''));

        return '<img src="' . e($src) . '" alt="' . $alt . '">';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function tableAttributes(array $node): string
    {
        $attrs = '';
        $colspan = (int) data_get($node, 'attrs.colspan', 1);
        $rowspan = (int) data_get($node, 'attrs.rowspan', 1);

        if ($colspan > 1) {
            $attrs .= ' colspan="' . $colspan . '"';
        }

        if ($rowspan > 1) {
            $attrs .= ' rowspan="' . $rowspan . '"';
        }

        return $attrs;
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//') && ! str_contains($url, '..')) {
            return true;
        }

        return Str::startsWith(Str::lower($url), ['https://', 'http://']);
    }
}
