<?php

declare(strict_types=1);

it('keeps exactly eleven intentionally unscoped event model exceptions', function (): void {
    $repositoryPath = dirname(__DIR__, 3);
    $modelsPath = 'packages/events/src/Models';

    $exceptions = [];
    $modelIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $repositoryPath . DIRECTORY_SEPARATOR . $modelsPath,
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($modelIterator as $modelFile) {
        if (! $modelFile instanceof SplFileInfo || $modelFile->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($modelFile->getPathname());

        if ($contents === false) {
            throw new RuntimeException("Unable to read event model file [{$modelFile->getPathname()}].");
        }

        if (
            preg_match('/use (HasOwner|ScopesByEventOwner)/', $contents) === 1
            || preg_match('/^[[:space:]]*(final |abstract )?class [A-Za-z_]/m', $contents) !== 1
        ) {
            continue;
        }

        $relativePath = mb_substr($modelFile->getPathname(), mb_strlen($repositoryPath) + 1);

        $exceptions[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    sort($exceptions);

    expect($exceptions)->toHaveCount(11)
        ->and($exceptions)->toEqual([
            'packages/events/src/Models/EventRole.php',
            'packages/events/src/Models/EventSeriesItemPivot.php',
            'packages/events/src/Models/EventSubmission.php',
            'packages/events/src/Models/EventTaxonomy.php',
            'packages/events/src/Models/EventTerm.php',
            'packages/events/src/Models/EventTermPolicy.php',
            'packages/events/src/Models/FacilityType.php',
            'packages/events/src/Models/Venue.php',
            'packages/events/src/Models/VenueFacility.php',
            'packages/events/src/Models/VenueSpace.php',
            'packages/events/src/Models/VenueSpaceType.php',
        ]);
});
