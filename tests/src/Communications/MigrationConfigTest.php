<?php

declare(strict_types=1);

test('communication migrations use the same table config keys as their models', function (): void {
    $migrationPath = __DIR__ . '/../../../packages/communications/database/migrations';
    $contents = collect(glob($migrationPath . '/*.php'))
        ->map(fn (string $file): string => file_get_contents($file))
        ->implode("\n");

    foreach ([
        'batches',
        'threads',
        'recipients',
        'contents',
        'deliveries',
        'attempts',
        'events',
        'templates',
        'template_versions',
        'preferences',
        'suppressions',
        'attachments',
        'references',
        'tracking_tokens',
    ] as $key) {
        expect($contents)->toContain("communications.database.tables.{$key}");
    }

    expect($contents)->not->toMatch('/communications\\.database\\.tables\\.communication_/');
});
