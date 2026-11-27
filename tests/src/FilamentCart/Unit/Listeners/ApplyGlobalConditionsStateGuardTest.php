<?php

declare(strict_types=1);

it('uses request-scoped Context guard instead of static mutable action state', function (): void {
    $repoRoot = dirname(__DIR__, 5);
    $path = $repoRoot . '/packages/cart/src/Actions/ReconcileGlobalConditions.php';

    expect($path)->toBeFile();

    $content = file_get_contents($path);

    expect($content)
        ->toBeString()
        ->toContain('Context::scope(')
        ->toContain('Context::getHidden(')
        ->not->toContain('private static bool $applying');
});
