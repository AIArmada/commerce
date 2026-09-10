<?php

declare(strict_types=1);

use AIArmada\Docs\Numbering\Contracts\DocumentNumberStrategy;
use AIArmada\Docs\Numbering\DocumentNumberRegistry;

final class DocsTestNumberStrategy implements DocumentNumberStrategy
{
    public function generate(string $docType): string
    {
        return 'LAZY-' . $docType;
    }
}

test('numbering strategies are resolved from config lazily', function (): void {
    $registry = new DocumentNumberRegistry;

    config()->set('docs.types.invoice.numbering.strategy', DocsTestNumberStrategy::class);

    expect($registry->generate('invoice'))->toBe('LAZY-invoice');
});
