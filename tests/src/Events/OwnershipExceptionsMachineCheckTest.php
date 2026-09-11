<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('keeps exactly eleven intentionally unscoped event model exceptions', function (): void {
    $repositoryPath = dirname(__DIR__, 3);
    $modelsPath = 'packages/events/src/Models';

    $unscopedModels = new Process([
        'rg',
        '--files-without-match',
        '--glob',
        '*.php',
        'use (HasOwner|ScopesByEventOwner)',
        $modelsPath,
    ], $repositoryPath);
    $unscopedModels->mustRun();

    $modelFiles = new Process([
        'rg',
        '--files-with-matches',
        '--glob',
        '*.php',
        '^[[:space:]]*(final |abstract )?class [A-Za-z_]',
        $modelsPath,
    ], $repositoryPath);
    $modelFiles->mustRun();

    $exceptions = array_values(array_intersect(
        array_filter(
            array_map('trim', preg_split('/\R/', $unscopedModels->getOutput()) ?: []),
            static fn (string $path): bool => $path !== '',
        ),
        array_filter(
            array_map('trim', preg_split('/\R/', $modelFiles->getOutput()) ?: []),
            static fn (string $path): bool => $path !== '',
        ),
    ));
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
