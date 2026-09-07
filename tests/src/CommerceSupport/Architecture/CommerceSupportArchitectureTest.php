<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

function readCommerceSupportComposerManifest(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new LogicException("Unable to read Composer manifest: {$path}");
    }

    $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($manifest)) {
        throw new LogicException("Composer manifest must decode to an array: {$path}");
    }

    return $manifest;
}

it('keeps commerce-support source independent of downstream package namespaces', function (): void {
    $repositoryPath = dirname(__DIR__, 4);
    $manifestPaths = glob("{$repositoryPath}/packages/*/composer.json") ?: [];
    $downstreamNamespaces = [];

    foreach ($manifestPaths as $manifestPath) {
        $manifest = readCommerceSupportComposerManifest($manifestPath);

        if (($manifest['name'] ?? null) === 'aiarmada/commerce-support') {
            continue;
        }

        $autoload = $manifest['autoload'] ?? [];
        $psr4 = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];

        if (! is_array($psr4)) {
            continue;
        }

        foreach (array_keys($psr4) as $namespace) {
            if (is_string($namespace) && str_starts_with($namespace, 'AIArmada\\')) {
                $downstreamNamespaces[] = mb_rtrim($namespace, '\\');
            }
        }
    }

    $downstreamNamespaces = array_values(array_unique($downstreamNamespaces));
    sort($downstreamNamespaces);

    $violations = [];
    $filesystem = new Filesystem;

    foreach ($filesystem->allFiles("{$repositoryPath}/packages/commerce-support/src") as $file) {
        $contents = $file->getContents();

        foreach ($downstreamNamespaces as $namespace) {
            if (str_contains($contents, "{$namespace}\\")) {
                $violations[] = "{$file->getPathname()} imports {$namespace}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('keeps commerce-support Composer requirements free of sibling packages', function (): void {
    $repositoryPath = dirname(__DIR__, 4);
    $manifest = readCommerceSupportComposerManifest(
        "{$repositoryPath}/packages/commerce-support/composer.json"
    );
    $manifestPaths = glob("{$repositoryPath}/packages/*/composer.json") ?: [];
    $siblingPackageNames = [];

    foreach ($manifestPaths as $manifestPath) {
        $siblingManifest = readCommerceSupportComposerManifest($manifestPath);
        $packageName = $siblingManifest['name'] ?? null;

        if (is_string($packageName) && $packageName !== 'aiarmada/commerce-support') {
            $siblingPackageNames[] = $packageName;
        }
    }

    $requirements = $manifest['require'] ?? [];
    $requiredSiblingPackages = is_array($requirements)
        ? array_values(array_intersect(array_keys($requirements), $siblingPackageNames))
        : [];

    expect($requiredSiblingPackages)->toBe([]);
});

it('keeps addressing composer requirements free of consumer packages', function (): void {
    // Canonical-addressing doctrine: consumers hard-require addressing;
    // addressing itself must require nothing beyond the foundation.
    $repositoryPath = dirname(__DIR__, 4);
    $manifest = readCommerceSupportComposerManifest(
        "{$repositoryPath}/packages/addressing/composer.json"
    );

    $requirements = $manifest['require'] ?? [];
    $allowed = ['php', 'aiarmada/commerce-support', 'spatie/laravel-package-tools'];
    $extra = is_array($requirements)
        ? array_values(array_diff(array_keys($requirements), $allowed))
        : [];

    expect($extra)->toBe([]);
});

it('keeps addressing source independent of consumer namespaces', function (): void {
    // Identity/addressing consumers that must never be imported back.
    $consumerNamespaces = [
        'AIArmada\\Customers',
        'AIArmada\\Persons',
        'AIArmada\\Orders',
        'AIArmada\\Events',
    ];

    $repositoryPath = dirname(__DIR__, 4);
    $filesystem = new Filesystem;

    $violations = [];

    foreach ($filesystem->allFiles("{$repositoryPath}/packages/addressing/src") as $file) {
        $contents = $file->getContents();

        foreach ($consumerNamespaces as $namespace) {
            if (str_contains($contents, "{$namespace}\\")) {
                $violations[] = "{$file->getPathname()} imports {$namespace}";
            }
        }
    }

    expect($violations)->toBe([]);
});
