<?php

declare(strict_types=1);

it('keeps exactly eleven intentionally unscoped event model exceptions', function (): void {
    $repositoryPath = dirname(__DIR__, 3);
    $featuresPath = 'packages/events/src/Features';

    $exceptions = [];
    $modelIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $repositoryPath . DIRECTORY_SEPARATOR . $featuresPath,
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($modelIterator as $modelFile) {
        if (! $modelFile instanceof SplFileInfo || $modelFile->getExtension() !== 'php') {
            continue;
        }

        $path = $modelFile->getPathname();

        if (! str_contains($path, DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read event model file [{$path}].");
        }

        if (
            preg_match('/use (HasOwner|ScopesByEventOwner)/', $contents) === 1
            || preg_match('/^[[:space:]]*(final |abstract )?class [A-Za-z_]/m', $contents) !== 1
        ) {
            continue;
        }

        $relativePath = mb_substr($path, mb_strlen($repositoryPath) + 1);

        $exceptions[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    sort($exceptions);

    expect($exceptions)->toHaveCount(11)
        ->and($exceptions)->toEqual([
            'packages/events/src/Features/Catalog/Models/EventRole.php',
            'packages/events/src/Features/Catalog/Models/EventSeriesItemPivot.php',
            'packages/events/src/Features/Governance/Models/EventSubmission.php',
            'packages/events/src/Features/Taxonomy/Models/EventTaxonomy.php',
            'packages/events/src/Features/Taxonomy/Models/EventTerm.php',
            'packages/events/src/Features/Taxonomy/Models/EventTermPolicy.php',
            'packages/events/src/Features/Venues/Models/FacilityType.php',
            'packages/events/src/Features/Venues/Models/Venue.php',
            'packages/events/src/Features/Venues/Models/VenueFacility.php',
            'packages/events/src/Features/Venues/Models/VenueSpace.php',
            'packages/events/src/Features/Venues/Models/VenueSpaceType.php',
        ]);
});
