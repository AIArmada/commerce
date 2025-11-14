<?php

declare(strict_types=1);

namespace AIArmada\Docs\Numbering;

use AIArmada\Docs\Numbering\Contracts\DocumentNumberStrategy;
use RuntimeException;

class NumberStrategyRegistry
{
    /**
     * @var array<string, DocumentNumberStrategy>
     */
    protected array $strategies = [];

    /**
     * Register a numbering strategy for a document type.
     */
    public function register(string $docType, DocumentNumberStrategy $strategy): void
    {
        $this->strategies[$docType] = $strategy;
    }

    /**
     * Get the numbering strategy for a document type.
     */
    public function get(string $docType): ?DocumentNumberStrategy
    {
        return $this->strategies[$docType] ?? null;
    }

    /**
     * Generate a document number using the registered strategy.
     */
    public function generate(string $docType): string
    {
        $strategy = $this->get($docType);

        if (! $strategy) {
            throw new RuntimeException("No numbering strategy registered for doc type: {$docType}");
        }

        return $strategy->generate($docType);
    }
}
