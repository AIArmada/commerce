<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Normalization;

final class EventContentNormalizer
{
    public function normalizeTitle(string $title): string
    {
        return mb_trim(preg_replace('/\s+/', ' ', $title) ?? $title);
    }

    public function normalizeSummary(?string $summary): ?string
    {
        if ($summary === null || $summary === '') {
            return null;
        }

        return mb_trim(strip_tags($summary));
    }

    public function normalizeDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        return mb_trim($description);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function normalize(array $content): array
    {
        $normalized = $content;

        if (isset($normalized['title'])) {
            $normalized['title'] = $this->normalizeTitle($normalized['title']);
        }

        if (isset($normalized['summary'])) {
            $normalized['summary'] = $this->normalizeSummary($normalized['summary']);
        }

        if (isset($normalized['description'])) {
            $normalized['description'] = $this->normalizeDescription($normalized['description']);
        }

        return $normalized;
    }
}
