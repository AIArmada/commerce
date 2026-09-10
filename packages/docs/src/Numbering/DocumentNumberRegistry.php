<?php

declare(strict_types=1);

namespace AIArmada\Docs\Numbering;

use AIArmada\Docs\Numbering\Contracts\DocumentNumberStrategy;
use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;
use Illuminate\Support\Str;

/**
 * Resolves document numbering strategies lazily for the current container scope.
 */
final class DocumentNumberRegistry
{
    /**
     * @var array<string, DocumentNumberStrategy>
     */
    private array $strategies = [];

    /**
     * Register a numbering strategy for a document type.
     */
    public function register(string $docType, DocumentNumberStrategy $strategy): void
    {
        $this->strategies[$docType] = $strategy;
    }

    /**
     * Resolve the numbering strategy for a document type.
     */
    public function get(string $docType): DocumentNumberStrategy
    {
        return $this->strategies[$docType] ??= $this->resolveStrategy($docType);
    }

    /**
     * Generate a document number using the resolved strategy.
     */
    public function generate(string $docType): string
    {
        return $this->get($docType)->generate($docType);
    }

    private function resolveStrategy(string $docType): DocumentNumberStrategy
    {
        /** @var array<string, mixed> $typeConfig */
        $typeConfig = config("docs.types.{$docType}", []);

        $explicitStrategy = data_get($typeConfig, 'numbering.strategy');

        if (is_string($explicitStrategy)
            && class_exists($explicitStrategy)
            && is_subclass_of($explicitStrategy, DocumentNumberStrategy::class)) {
            return app($explicitStrategy);
        }

        $conventionClass = 'App\\Numbering\\' . Str::studly($docType) . 'NumberStrategy';

        if (class_exists($conventionClass) && is_subclass_of($conventionClass, DocumentNumberStrategy::class)) {
            return app($conventionClass);
        }

        return app(DefaultNumberStrategy::class);
    }
}
