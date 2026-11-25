<?php

declare(strict_types=1);

arch('events')
    ->expect('AIArmada\Events')
    ->not->toUse('App');

it('keeps package php free of app namespace and institution dependencies', function (): void {
    $packagePath = dirname(__DIR__, 4) . '/packages/events';

    $violations = [];

    foreach (['src', 'config', 'database', 'resources', 'routes'] as $directory) {
        $path = $packagePath . DIRECTORY_SEPARATOR . $directory;

        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $pathname = $file->getPathname();
            $contents = file_get_contents($pathname);

            if ($contents === false) {
                throw new RuntimeException("Unable to read [{$pathname}].");
            }

            if (str_contains($contents, 'App\\') || str_contains($contents, 'Institution')) {
                $violations[] = $pathname;
            }
        }
    }

    expect($violations)->toBe([]);
});
