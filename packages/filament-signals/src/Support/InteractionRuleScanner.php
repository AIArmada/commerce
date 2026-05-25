<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

final class InteractionRuleScanner
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @return list<array{name: string, selector: string|null, page_pattern: string|null, settings: array<string, mixed>|null, confidence: int, confidence_note: string}>
     */
    public function scan(string $pageUrl, string $triggerType, int $maxCandidates = 25): array
    {
        $normalizedTriggerType = $this->normalizeTriggerType($triggerType);
        $html = $this->fetchHtml($pageUrl);

        if ($html === null) {
            return [];
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $nodes = $this->nodesForTriggerType($xpath, $normalizedTriggerType);
        $pagePattern = $this->pathPattern($pageUrl);

        /** @var array<string, array{name: string, selector: string|null, page_pattern: string|null, settings: array<string, mixed>|null, confidence: int, confidence_note: string}> $candidatesByKey */
        $candidatesByKey = [];

        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            $selector = $this->selectorForNode($node, $normalizedTriggerType);
            $name = $this->nameForNode($node, $normalizedTriggerType);
            $settings = $normalizedTriggerType === 'media'
                ? ['once_per_session' => false]
                : null;
            $confidence = $this->confidenceForCandidate($selector, $normalizedTriggerType, true);

            if ($selector === null && $normalizedTriggerType !== 'media') {
                continue;
            }

            $key = ($selector ?? 'no-selector') . '|' . $name;

            if (array_key_exists($key, $candidatesByKey)) {
                continue;
            }

            $candidatesByKey[$key] = [
                'name' => $name,
                'selector' => $selector,
                'page_pattern' => $pagePattern,
                'settings' => $settings,
                'confidence' => $confidence['score'],
                'confidence_note' => $confidence['note'],
            ];

            if (count($candidatesByKey) >= max(1, $maxCandidates)) {
                break;
            }
        }

        return array_values($candidatesByKey);
    }

    /**
     * @param  list<string>  $sourcePaths
     * @return list<array{name: string, selector: string|null, page_pattern: string|null, settings: array<string, mixed>|null, confidence: int, confidence_note: string}>
     */
    public function scanLocalSource(
        string $triggerType,
        ?string $routePath,
        int $maxCandidates = 25,
        array $sourcePaths = [],
    ): array {
        $normalizedTriggerType = $this->normalizeTriggerType($triggerType);
        $pagePattern = $this->normalizeRoutePath($routePath);
        $paths = $sourcePaths !== []
            ? $sourcePaths
            : $this->defaultSourcePaths();

        /** @var array<string, array{name: string, selector: string|null, page_pattern: string|null, settings: array<string, mixed>|null, confidence: int, confidence_note: string}> $candidatesByKey */
        $candidatesByKey = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! $this->filesystem->isDirectory($path)) {
                continue;
            }

            foreach ($this->filesystem->allFiles($path) as $file) {
                $extension = mb_strtolower((string) $file->getExtension());

                if (! in_array($extension, ['php', 'blade.php'], true)) {
                    continue;
                }

                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $lines = preg_split('/\R/u', $this->filesystem->get($file->getPathname())) ?: [];

                foreach ($lines as $lineNumber => $line) {
                    $candidate = $this->candidateFromSourceLine(
                        line: $line,
                        triggerType: $normalizedTriggerType,
                        pagePattern: $pagePattern,
                        sourceFile: $relativePath,
                        sourceLine: $lineNumber + 1,
                    );

                    if ($candidate === null) {
                        continue;
                    }

                    $key = ($candidate['selector'] ?? 'no-selector') . '|' . $candidate['name'] . '|' . $relativePath;

                    if (array_key_exists($key, $candidatesByKey)) {
                        continue;
                    }

                    $candidatesByKey[$key] = $candidate;

                    if (count($candidatesByKey) >= max(1, $maxCandidates)) {
                        break 3;
                    }
                }
            }
        }

        return array_values($candidatesByKey);
    }

    /**
     * @return list<string>
     */
    public function defaultSourcePaths(): array
    {
        $paths = [
            base_path('resources/views'),
            base_path('app/Livewire'),
            base_path('app/Livewire/Volt'),
        ];

        $unique = [];

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            $unique[$path] = $path;
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    public function discoverRoutePatterns(): array
    {
        $patterns = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $methods = $route->methods();

            if (! in_array('GET', $methods, true) || in_array('HEAD', $methods, true) && count($methods) === 1) {
                continue;
            }

            $uri = '/' . mb_ltrim($route->uri(), '/');

            if ($uri === '/{fallbackPlaceholder}' || str_contains($uri, '{')) {
                continue;
            }

            $patterns[$uri] = $uri;
        }

        ksort($patterns);

        return array_values($patterns);
    }

    private function fetchHtml(string $pageUrl): ?string
    {
        $response = Http::timeout(15)
            ->withHeaders(['Accept' => 'text/html'])
            ->get($pageUrl);

        if (! $response->successful()) {
            return null;
        }

        $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        return $response->body();
    }

    private function normalizeTriggerType(string $triggerType): string
    {
        return match ($triggerType) {
            'accordion', 'media', 'youtube' => $triggerType,
            default => 'click',
        };
    }

    private function normalizeRoutePath(?string $routePath): ?string
    {
        if (! is_string($routePath)) {
            return null;
        }

        $normalized = mb_trim($routePath);

        if ($normalized === '') {
            return null;
        }

        if (! str_starts_with($normalized, '/')) {
            $normalized = '/' . $normalized;
        }

        return $normalized;
    }

    private function nodesForTriggerType(DOMXPath $xpath, string $triggerType): DOMNodeList
    {
        return match ($triggerType) {
            'accordion' => $xpath->query('//details/summary | //*[@aria-expanded]'),
            'media' => $xpath->query('//audio | //video'),
            'youtube' => $xpath->query('//iframe[contains(@src, "youtube.com") or contains(@src, "youtu.be")] | //*[@data-youtube]'),
            default => $xpath->query('//a[@href] | //button | //*[@role="button"] | //*[@onclick] | //input[@type="button" or @type="submit"]'),
        };
    }

    private function pathPattern(string $pageUrl): ?string
    {
        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    private function selectorForNode(DOMElement $node, string $triggerType): ?string
    {
        if ($triggerType === 'media') {
            return null;
        }

        $id = $this->normalizedAttribute($node, 'id');

        if ($id !== null) {
            return '#' . $id;
        }

        foreach (['data-signals-track', 'data-track', 'data-testid', 'name'] as $attribute) {
            $value = $this->normalizedAttribute($node, $attribute);

            if ($value !== null) {
                return '[' . $attribute . '="' . $value . '"]';
            }
        }

        $tagName = mb_strtolower($node->tagName);
        $href = $this->normalizedAttribute($node, 'href');

        if ($tagName === 'a' && $href !== null) {
            return 'a[href="' . $href . '"]';
        }

        $className = $this->normalizedAttribute($node, 'class');

        if ($className !== null) {
            $classes = array_values(array_filter(
                preg_split('/\s+/', $className) ?: [],
                static fn (string $class): bool => $class !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $class) === 1,
            ));

            if ($classes !== []) {
                return $tagName . '.' . implode('.', array_slice($classes, 0, 2));
            }
        }

        if ($tagName === 'summary') {
            return 'details > summary';
        }

        return $tagName;
    }

    private function nameForNode(DOMElement $node, string $triggerType): string
    {
        $rawText = mb_trim((string) preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
        $tagName = mb_strtolower($node->tagName);

        if ($rawText !== '') {
            return mb_substr($rawText, 0, 120);
        }

        if ($triggerType === 'youtube') {
            return 'YouTube Embed Interaction';
        }

        if ($triggerType === 'media') {
            $source = $this->normalizedAttribute($node, 'src');

            return $source !== null
                ? 'Media Interaction (' . mb_substr($source, 0, 80) . ')'
                : 'Media Interaction';
        }

        return ucfirst($tagName) . ' Interaction';
    }

    private function normalizedAttribute(DOMElement $element, string $attribute): ?string
    {
        $value = mb_trim($element->getAttribute($attribute));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{name: string, selector: string|null, page_pattern: string|null, settings: array<string, mixed>|null, confidence: int, confidence_note: string}|null
     */
    private function candidateFromSourceLine(
        string $line,
        string $triggerType,
        ?string $pagePattern,
        string $sourceFile,
        int $sourceLine,
    ): ?array {
        $lineText = mb_trim($line);

        if ($lineText === '' || str_starts_with($lineText, '//') || str_starts_with($lineText, '*')) {
            return null;
        }

        $isMatch = match ($triggerType) {
            'accordion' => str_contains($lineText, '<summary') || str_contains($lineText, 'aria-expanded'),
            'media' => str_contains($lineText, '<audio') || str_contains($lineText, '<video'),
            'youtube' => str_contains($lineText, 'youtube.com') || str_contains($lineText, 'youtu.be') || str_contains($lineText, 'data-youtube') || str_contains($lineText, '<iframe'),
            default => str_contains($lineText, '<a ') || str_contains($lineText, '<button') || str_contains($lineText, 'wire:click') || str_contains($lineText, '@click') || str_contains($lineText, 'onclick=') || str_contains($lineText, 'role="button"') || str_contains($lineText, "role='button'"),
        };

        if (! $isMatch) {
            return null;
        }

        $selector = $this->selectorFromSourceLine($lineText, $triggerType);

        if ($selector === null && $triggerType !== 'media') {
            return null;
        }

        $name = $this->nameFromSourceLine($lineText, $triggerType);
        $confidence = $this->confidenceForCandidate($selector, $triggerType, false);

        return [
            'name' => $name,
            'selector' => $selector,
            'page_pattern' => $pagePattern,
            'settings' => [
                'source_file' => $sourceFile,
                'source_line' => $sourceLine,
                'scan_source' => 'local_code',
            ],
            'confidence' => $confidence['score'],
            'confidence_note' => $confidence['note'],
        ];
    }

    private function selectorFromSourceLine(string $line, string $triggerType): ?string
    {
        if ($triggerType === 'media') {
            return null;
        }

        foreach (['id', 'data-signals-track', 'data-track', 'data-testid', 'name'] as $attribute) {
            if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*["\']([^"\']+)["\']/u', $line, $matches) === 1) {
                $value = mb_trim((string) $matches[1]);

                if ($value === '') {
                    continue;
                }

                if ($attribute === 'id') {
                    return '#' . $value;
                }

                return '[' . $attribute . '="' . $value . '"]';
            }
        }

        if (preg_match('/\bclass\s*=\s*["\']([^"\']+)["\']/u', $line, $classMatches) === 1) {
            $classes = array_values(array_filter(
                preg_split('/\s+/u', (string) $classMatches[1]) ?: [],
                static fn (string $class): bool => $class !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $class) === 1,
            ));

            if ($classes !== []) {
                $tag = $this->tagNameFromSourceLine($line) ?? 'button';

                return mb_strtolower($tag) . '.' . implode('.', array_slice($classes, 0, 2));
            }
        }

        $tagName = $this->tagNameFromSourceLine($line);

        if ($tagName === null) {
            return null;
        }

        if (mb_strtolower($tagName) === 'summary') {
            return 'details > summary';
        }

        return mb_strtolower($tagName);
    }

    private function nameFromSourceLine(string $line, string $triggerType): string
    {
        $plain = mb_trim((string) preg_replace('/\s+/u', ' ', strip_tags($line)));

        if ($plain !== '') {
            return mb_substr($plain, 0, 120);
        }

        if ($triggerType === 'youtube') {
            return 'YouTube Embed Interaction';
        }

        if ($triggerType === 'media') {
            return 'Media Interaction';
        }

        $tagName = $this->tagNameFromSourceLine($line);

        return $tagName !== null
            ? ucfirst(mb_strtolower($tagName)) . ' Interaction'
            : 'UI Interaction';
    }

    private function tagNameFromSourceLine(string $line): ?string
    {
        if (preg_match('/<\s*([a-zA-Z0-9:-]+)/u', $line, $matches) !== 1) {
            return null;
        }

        $tagName = mb_trim((string) $matches[1]);

        return $tagName !== '' ? $tagName : null;
    }

    /**
     * @return array{score: int, note: string}
     */
    private function confidenceForCandidate(?string $selector, string $triggerType, bool $fromRenderedHtml): array
    {
        if ($triggerType === 'media' && $selector === null) {
            return [
                'score' => 72,
                'note' => 'Media trigger uses default audio/video selector.',
            ];
        }

        if ($selector === null || $selector === '') {
            return [
                'score' => 38,
                'note' => 'No selector found; likely unstable and should be reviewed manually.',
            ];
        }

        if (str_starts_with($selector, '#') || str_contains($selector, '[data-')) {
            return [
                'score' => $fromRenderedHtml ? 95 : 90,
                'note' => 'Strong selector (id or data attribute).',
            ];
        }

        if (preg_match('/\.[A-Za-z0-9_-]+\./', $selector) === 1 || str_contains($selector, '[')) {
            return [
                'score' => $fromRenderedHtml ? 82 : 78,
                'note' => 'Moderate selector (class/attribute combination).',
            ];
        }

        return [
            'score' => $fromRenderedHtml ? 64 : 58,
            'note' => 'Tag-based selector only; may be broad and should be tightened.',
        ];
    }
}
