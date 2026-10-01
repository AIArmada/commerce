<?php

declare(strict_types=1);

arch('events')
    ->expect('AIArmada\Events')
    ->not->toUse('App');

it('keeps package php free of app namespace and institution dependencies', function (): void {
    $packagePath = dirname(__DIR__, 4) . '/packages/events';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packagePath, FilesystemIterator::SKIP_DOTS),
    );

    $violations = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents === false) {
            throw new RuntimeException("Unable to read [{$file->getPathname()}].");
        }

        if (str_contains($contents, 'App\\') || str_contains($contents, 'Institution')) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBe([]);
});
