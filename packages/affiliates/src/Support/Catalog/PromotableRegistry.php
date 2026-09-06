<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Catalog;

use AIArmada\Affiliates\Contracts\PromotableProviderInterface;
use Illuminate\Support\Collection;

/**
 * Tagged registrar for promotable providers (products, ticketing, ...).
 *
 * Falls back to deriving subjects from active product/category rule
 * conditions when no provider is registered, so catalog sync works
 * with zero integration on day one.
 */
final class PromotableRegistry
{
    /** @var array<int, PromotableProviderInterface> */
    private array $providers = [];

    public function register(PromotableProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_key: string, title: string, url: string, amount_minor: int|null, currency: string|null, context: array<string, mixed>}>
     */
    public function all(?string $programId = null, int $maxSubjects = 500): Collection
    {
        $items = collect();

        foreach ($this->providers as $provider) {
            foreach ($provider->list($programId) as $row) {
                $items->push([
                    'subject_type' => $provider->type(),
                    'subject_key' => (string) ($row['subject_key'] ?? ''),
                    'title' => (string) ($row['title'] ?? $row['subject_key'] ?? ''),
                    'url' => (string) ($row['url'] ?? '/'),
                    'amount_minor' => isset($row['amount_minor']) ? (int) $row['amount_minor'] : null,
                    'currency' => $row['currency'] ?? null,
                    'context' => is_array($row['context'] ?? null) ? $row['context'] : [],
                ]);

                if ($items->count() >= $maxSubjects) {
                    return $items;
                }
            }
        }

        return $items;
    }

    public function hasProviders(): bool
    {
        return $this->providers !== [];
    }
}
