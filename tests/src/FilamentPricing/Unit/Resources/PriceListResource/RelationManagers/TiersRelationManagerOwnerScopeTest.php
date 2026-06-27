<?php

declare(strict_types=1);

it('uses OwnerQuery instead of dynamic owner-scope method checks for tier labels', function (): void {
    $source = file_get_contents(getcwd() . '/packages/filament-pricing/src/Resources/PriceListResource/RelationManagers/TiersRelationManager.php');

    expect($source)->not->toContain("method_exists(\$model, 'scopeForOwner')")
        ->and($source)->toContain('OwnerQuery::applyToEloquentBuilder');
});
